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
    private ?string $secondCustomerUsername = null;

    protected function tearDown(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        foreach ([$this->customerUsername, $this->secondCustomerUsername] as $username) {
            if (null === $username) {
                continue;
            }

            $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);

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

    public function testInvitingFriendsSendsValidEmailsAndReportsInvalidOnes(): void
    {
        $client = self::createClient();

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

        $client->loginUser($user, 'web');

        $crawler = $client->request('GET', '/profile/referrals');
        $token = $crawler->filter('#referral-invite-form input[name="_token"]')->attr('value');

        $client->request('POST', '/profile/referrals/invite', [
            'emails' => "friend@example.com, not-an-email\nanother.friend@example.com",
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/profile/referrals');

        $crawler = $client->followRedirect();

        self::assertSelectorTextContains('.flash-messages', '2 invitation(s) envoyée(s)');
        self::assertSelectorTextContains('.flash-messages', "1 adresse(s) e-mail invalide(s) n'ont pas été envoyée(s)");
    }

    public function testInvitingFriendsSkipsAlreadyRegisteredEmails(): void
    {
        $client = self::createClient();

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

        $existingUser = $userManager->createUser();
        $this->secondCustomerUsername = 'referral_test_existing_' . uniqid();
        $existingUser->setUsername($this->secondCustomerUsername);
        $existingUser->setEmail($this->secondCustomerUsername . '@example.com');
        $existingUser->setPlainPassword('irrelevant');
        $existingUser->setRoles(['ROLE_USER']);
        $existingUser->setEnabled(true);
        $userManager->updateUser($existingUser);

        $client->loginUser($user, 'web');

        $crawler = $client->request('GET', '/profile/referrals');
        $token = $crawler->filter('#referral-invite-form input[name="_token"]')->attr('value');

        $client->request('POST', '/profile/referrals/invite', [
            'emails' => sprintf('new.friend@example.com, %s', $existingUser->getEmail()),
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/profile/referrals');

        $client->followRedirect();

        self::assertSelectorTextContains('.flash-messages', '1 invitation(s) envoyée(s)');
        self::assertSelectorTextContains('.flash-messages', "1 adresse(s) e-mail possède(nt) déjà un compte et n'ont pas été invitée(s)");
    }

    public function testInvitingFriendsRejectsInvalidCsrfToken(): void
    {
        $client = self::createClient();

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

        $client->loginUser($user, 'web');

        $client->request('POST', '/profile/referrals/invite', [
            'emails' => 'friend@example.com',
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
