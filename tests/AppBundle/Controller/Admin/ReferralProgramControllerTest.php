<?php

namespace Tests\AppBundle\Controller\Admin;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\User;
use AppBundle\Security\UserManager;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional smoke test for the admin referral dashboard -- exercises the
 * real routing/security/form/Doctrine/Twig stack, which the unit-tested
 * services (ReferralRepository, ReferralLevelResolver, ...) don't cover on
 * their own.
 *
 * Needs an actually-persisted user: the "web" firewall's Doctrine user
 * provider reloads the authenticated user from the DB on every request, so
 * a transient in-memory User passed to loginUser() gets silently dropped
 * (redirect to /login) once the test client's second request reloads it.
 * The feature flag is forced on for this route via REFERRAL_PROGRAM_ENABLED
 * in .env.test.
 *
 * Each client request can reboot the kernel (a fresh container/EntityManager),
 * so entities fetched before a request are stale afterwards -- helpers here
 * always re-fetch from self::getContainer() rather than caching a manager.
 */
class ReferralProgramControllerTest extends WebTestCase
{
    private ?string $adminUsername = null;

    protected function tearDown(): void
    {
        if (null !== $this->adminUsername) {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $admin = $entityManager->getRepository(User::class)->findOneBy(['username' => $this->adminUsername]);

            if (null !== $admin) {
                $entityManager->remove($admin->getCustomer());
                $entityManager->remove($admin);
                $entityManager->flush();
            }
        }

        parent::tearDown();
    }

    private function loginAsAdmin(): KernelBrowser
    {
        $client = self::createClient();

        $userManager = self::getContainer()->get(UserManager::class);

        $admin = $userManager->createUser();
        $this->adminUsername = 'referral_test_admin_' . uniqid();
        $admin->setUsername($this->adminUsername);
        $admin->setEmail($this->adminUsername . '@example.com');
        $admin->setPlainPassword('irrelevant');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setEnabled(true);
        $userManager->updateUser($admin);

        $client->loginUser($admin, 'web');

        return $client;
    }

    public function testDashboardRendersForAdmin(): void
    {
        $client = $this->loginAsAdmin();

        $client->request('GET', '/admin/referral-program');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Referral Program');
    }

    public function testSubmittingWelcomeSettingsFormPersistsValues(): void
    {
        $client = $this->loginAsAdmin();

        $crawler = $client->request('GET', '/admin/referral-program');

        $form = $crawler->filter('form[name="referral_welcome_settings"]')->form();
        $form['referral_welcome_settings[referral_welcome_reward_type]'] = 'order_fixed_discount';
        $form['referral_welcome_settings[referral_welcome_reward_amount]'] = '777';
        $form['referral_welcome_settings[referral_welcome_coupon_validity_days]'] = '45';

        $client->submit($form);

        self::assertResponseRedirects('/admin/referral-program');

        $settingsManager = self::getContainer()->get(SettingsManager::class);
        self::assertSame(777, $settingsManager->get('referral_welcome_reward_amount'));
        self::assertSame(45, $settingsManager->get('referral_welcome_coupon_validity_days'));
    }

    public function testSubmittingLevelsFormPersistsValues(): void
    {
        $client = $this->loginAsAdmin();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $bronze = $entityManager->getRepository(ReferralLevel::class)->findOneBy([], ['position' => 'ASC']);

        // The migration seeds Bronze/Silver/Gold against the dev DB, but the
        // test DB's schema comes from doctrine:schema:update, not
        // migrations -- fall back to creating one so this test doesn't
        // depend on that seed data existing.
        $createdLevel = null;
        if (null === $bronze) {
            $bronze = new ReferralLevel();
            $bronze->setName('bronze');
            $bronze->setPosition(0);
            $bronze->setMinReferralCount(1);
            $bronze->setRewardType('order_fixed_discount');
            $bronze->setRewardAmount(500);
            $bronze->setCouponValidityDays(30);
            $bronze->setUsageLimit(1);
            $entityManager->persist($bronze);
            $entityManager->flush();
            $createdLevel = $bronze;
        }

        $originalMinReferralCount = $bronze->getMinReferralCount();

        $crawler = $client->request('GET', '/admin/referral-program');

        $form = $crawler->filter('form[name="form"]')->form();
        $form[sprintf('form[level_%d][minReferralCount]', $bronze->getId())] = (string) ($originalMinReferralCount + 1);

        $client->submit($form);

        self::assertResponseRedirects('/admin/referral-program');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $bronze = $entityManager->getRepository(ReferralLevel::class)->find($bronze->getId());
        self::assertSame($originalMinReferralCount + 1, $bronze->getMinReferralCount());

        if (null !== $createdLevel) {
            $entityManager->remove($bronze);
        } else {
            // Restore the seeded default so other tests relying on it aren't affected.
            $bronze->setMinReferralCount($originalMinReferralCount);
        }
        $entityManager->flush();
    }
}
