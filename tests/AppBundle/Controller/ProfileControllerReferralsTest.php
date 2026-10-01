<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Security\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test for the customer-facing /profile/referrals page.
 *
 * See ReferralProgramControllerTest for why this needs an actually
 * persisted user rather than a transient one passed to loginUser().
 */
class ProfileControllerReferralsTest extends WebTestCase
{
    private ?string $customerUsername = null;

    protected function tearDown(): void
    {
        if (null !== $this->customerUsername) {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $this->customerUsername]);

            if (null !== $user) {
                $entityManager->remove($user->getCustomer());
                $entityManager->remove($user);
                $entityManager->flush();
            }
        }

        parent::tearDown();
    }

    public function testPageGeneratesReferralCodeOnFirstVisitAndShowsLink(): void
    {
        $client = self::createClient();

        $userManager = self::getContainer()->get(UserManager::class);

        $user = $userManager->createUser();
        $this->customerUsername = 'referral_test_customer_' . uniqid();
        $user->setUsername($this->customerUsername);
        $user->setEmail($this->customerUsername . '@example.com');
        $user->setPlainPassword('irrelevant');
        $user->setRoles(['ROLE_USER']);
        $user->setEnabled(true);
        $userManager->updateUser($user);

        self::assertNull($user->getCustomer()->getReferralCode());

        $client->loginUser($user, 'web');

        $crawler = $client->request('GET', '/profile/referrals');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Invite friends');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $refreshedCustomer = $entityManager->getRepository(User::class)
            ->findOneBy(['username' => $this->customerUsername])
            ->getCustomer();

        self::assertNotNull($refreshedCustomer->getReferralCode());

        $linkInput = $crawler->filter('input[readonly]');
        self::assertStringContainsString($refreshedCustomer->getReferralCode(), $linkInput->attr('value'));
    }
}
