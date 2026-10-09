<?php

namespace Tests\AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Entity\Marketing\CampaignRecipient;
use AppBundle\Entity\Marketing\CampaignRecipientRepository;
use AppBundle\Entity\Marketing\EmailSuppression;
use AppBundle\Entity\Marketing\EmailSuppressionRepository;
use AppBundle\Entity\OptinConsent;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\OptinConsentRepository;
use AppBundle\Entity\User;
use AppBundle\Enum\Optin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The audience filters are three database queries behind interfaces the unit
 * tests stub out. A stubbed repository will happily agree with a query that
 * doesn't parse, so each one is run against the real schema here.
 */
class CampaignAudienceQueriesIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private array $usernames = [];
    private ?Campaign $campaign = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        // Cleaned up entirely through DQL, and from a cleared identity map:
        // deleting rows out from under managed objects and then flushing
        // leaves Doctrine reasoning about entities that no longer exist.
        $this->entityManager->clear();

        if (null !== $this->campaign && null !== $this->campaign->getId()) {
            $this->deleteBy('AppBundle\Entity\Marketing\CampaignRecipient r', 'r.campaign', $this->campaign->getId());
            $this->deleteBy('AppBundle\Entity\Marketing\Campaign c', 'c.id', $this->campaign->getId());
        }

        foreach ($this->usernames as $username) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);

            if (null === $user) {
                continue;
            }

            $userId = $user->getId();
            $customerId = $user->getCustomer()?->getId();

            $this->entityManager->clear();

            $this->deleteBy('AppBundle\Entity\OptinConsent o', 'o.user', $userId);
            $this->deleteBy('AppBundle\Entity\User u', 'u.id', $userId);

            if (null !== $customerId) {
                $this->deleteBy('AppBundle\Entity\Sylius\Customer c', 'c.id', $customerId);
            }
        }

        $this->entityManager->createQuery('DELETE AppBundle\Entity\Marketing\EmailSuppression s WHERE s.email LIKE :like')
            ->setParameter('like', 'audience_test_%')
            ->execute();

        parent::tearDown();
    }

    private function deleteBy(string $from, string $field, $value): void
    {
        $this->entityManager
            ->createQuery(sprintf('DELETE %s WHERE %s = :value', $from, $field))
            ->setParameter('value', $value)
            ->execute();
    }

    /**
     * Built directly rather than through UserManager, so the whole graph --
     * customer, user, consent -- is managed by this test's entity manager
     * and the consent's association to the user resolves on flush.
     */
    private function createUser(?bool $marketingConsent): User
    {
        $username = 'audience_test_' . uniqid();
        $this->usernames[] = $username;

        $customer = new Customer();
        $customer->setEmail($username . '@example.com');
        $customer->setEmailCanonical($username . '@example.com');

        $user = new User();
        $user->setUsername($username);
        $user->setUsernameCanonical($username);
        $user->setEmail($username . '@example.com');
        $user->setEmailCanonical($username . '@example.com');
        $user->setPassword('irrelevant');
        $user->setEnabled(true);
        $user->setCustomer($customer);

        if (null !== $marketingConsent) {
            $consent = new OptinConsent();
            $consent->setType(Optin::MARKETING);
            $consent->setAsked(true);
            $consent->setAccepted($marketingConsent);
            $user->addOptinConsent($consent);
        }

        $this->entityManager->persist($customer);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * The riskiest query of the three: it reaches customer ids through a
     * join on the user, which DQL only resolves at runtime.
     */
    public function testConsentLookupFindsOnlyCustomersWhoAccepted(): void
    {
        $accepted = $this->createUser(true);
        $refused = $this->createUser(false);
        $neverAsked = $this->createUser(null);

        $ids = array_map(
            fn (User $u) => $u->getCustomer()->getId(),
            [$accepted, $refused, $neverAsked]
        );

        /** @var OptinConsentRepository $repository */
        $repository = $this->entityManager->getRepository(OptinConsent::class);
        $consented = $repository->findCustomerIdsWithConsent(Optin::MARKETING, $ids);

        self::assertArrayHasKey($accepted->getCustomer()->getId(), $consented);
        self::assertArrayNotHasKey($refused->getCustomer()->getId(), $consented);
        self::assertArrayNotHasKey($neverAsked->getCustomer()->getId(), $consented);
    }

    public function testWithdrawnConsentIsNotConsent(): void
    {
        $user = $this->createUser(true);

        foreach ($user->getOptinConsents() as $consent) {
            $consent->setWithdrawedAt(new \DateTime());
        }
        $this->entityManager->flush();

        /** @var OptinConsentRepository $repository */
        $repository = $this->entityManager->getRepository(OptinConsent::class);

        self::assertSame([], $repository->findCustomerIdsWithConsent(
            Optin::MARKETING,
            [$user->getCustomer()->getId()]
        ));
    }

    public function testFrequencyCapQueryOnlyCountsActualSends(): void
    {
        $this->campaign = new Campaign();
        $this->campaign->setName('Test campaign');
        $this->campaign->setSegment('champions');
        $this->entityManager->persist($this->campaign);

        $sent = CampaignRecipient::create($this->campaign, 'audience_test_sent@example.com');
        $sent->markAsSent('message-id');
        $this->entityManager->persist($sent);

        // Left out of the last campaign rather than sent to -- hasn't been
        // bothered, so mustn't be held back again.
        $suppressed = CampaignRecipient::create($this->campaign, 'audience_test_suppressed@example.com');
        $suppressed->markAsSuppressed();
        $this->entityManager->persist($suppressed);

        $this->entityManager->flush();

        /** @var CampaignRecipientRepository $repository */
        $repository = $this->entityManager->getRepository(CampaignRecipient::class);

        $recent = $repository->findRecentlyEmailed(
            ['audience_test_sent@example.com', 'audience_test_suppressed@example.com'],
            new \DateTime('-7 days')
        );

        self::assertArrayHasKey('audience_test_sent@example.com', $recent);
        self::assertArrayNotHasKey('audience_test_suppressed@example.com', $recent);
    }

    public function testFrequencyCapQueryIgnoresOlderSends(): void
    {
        $this->campaign = new Campaign();
        $this->campaign->setName('Test campaign');
        $this->campaign->setSegment('champions');
        $this->entityManager->persist($this->campaign);

        $old = CampaignRecipient::create($this->campaign, 'audience_test_old@example.com');
        $old->markAsSent();
        $this->entityManager->persist($old);
        $this->entityManager->flush();

        // Backdate past the window.
        $this->entityManager->createQuery('UPDATE AppBundle\Entity\Marketing\CampaignRecipient r SET r.sentAt = :then WHERE r = :recipient')
            ->setParameter('then', new \DateTime('-30 days'))
            ->setParameter('recipient', $old)
            ->execute();

        /** @var CampaignRecipientRepository $repository */
        $repository = $this->entityManager->getRepository(CampaignRecipient::class);

        self::assertSame([], $repository->findRecentlyEmailed(
            ['audience_test_old@example.com'],
            new \DateTime('-7 days')
        ));
    }

    public function testSuppressionLookupMatchesAcrossStreams(): void
    {
        $this->entityManager->persist(EmailSuppression::create(
            'audience_test_bounced@example.com',
            'outbound',
            EmailSuppression::REASON_HARD_BOUNCE
        ));
        $this->entityManager->flush();

        /** @var EmailSuppressionRepository $repository */
        $repository = $this->entityManager->getRepository(EmailSuppression::class);

        // Suppressed on the transactional stream, but a dead address is dead
        // for marketing too.
        $suppressed = $repository->findSuppressed([
            'Audience_Test_Bounced@Example.com',
            'audience_test_fine@example.com',
        ]);

        self::assertArrayHasKey('audience_test_bounced@example.com', $suppressed);
        self::assertArrayNotHasKey('audience_test_fine@example.com', $suppressed);
    }
}
