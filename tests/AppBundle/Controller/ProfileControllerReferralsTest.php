<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Security\UserManager;
use AppBundle\Service\SettingsManager;
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

        // The runtime on/off switch defaults to off regardless of the env
        // var, so it must be turned on explicitly for this page to be reachable.
        $settingsManager = self::getContainer()->get(SettingsManager::class);
        $settingsManager->set('referral_program_active', '1');
        $settingsManager->flush();

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
        // The app under test runs with COOPCYCLE_LOCALE=fr.
        self::assertSelectorTextContains('body', 'Inviter des amis');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $refreshedCustomer = $entityManager->getRepository(User::class)
            ->findOneBy(['username' => $this->customerUsername])
            ->getCustomer();

        self::assertNotNull($refreshedCustomer->getReferralCode());

        $linkInput = $crawler->filter('input[readonly]');
        self::assertStringContainsString($refreshedCustomer->getReferralCode(), $linkInput->attr('value'));
    }
}
