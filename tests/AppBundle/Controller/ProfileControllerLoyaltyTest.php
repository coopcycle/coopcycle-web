<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Loyalty\LoyaltyPointsEntry;
use AppBundle\Entity\Loyalty\LoyaltyReward;
use AppBundle\Entity\User;
use AppBundle\Security\UserManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test for the customer-facing /profile/loyalty page.
 *
 * See ReferralProgramControllerTest for why this needs an actually
 * persisted user rather than a transient one passed to loginUser().
 */
class ProfileControllerLoyaltyTest extends WebTestCase
{
    private ?string $customerUsername = null;
    private ?int $rewardId = null;

    protected function tearDown(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (null !== $this->customerUsername) {
            $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $this->customerUsername]);

            if (null !== $user) {
                $entityManager->createQuery('DELETE AppBundle\Entity\Loyalty\LoyaltyPointsEntry e WHERE e.customer = :customer')
                    ->setParameter('customer', $user->getCustomer())
                    ->execute();
                $entityManager->remove($user->getCustomer());
                $entityManager->remove($user);
                $entityManager->flush();
            }
        }

        if (null !== $this->rewardId) {
            $entityManager->createQuery('DELETE AppBundle\Entity\Loyalty\LoyaltyReward r WHERE r.id = :id')
                ->setParameter('id', $this->rewardId)
                ->execute();
        }

        parent::tearDown();
    }

    private function activateProgram(): void
    {
        $settingsManager = self::getContainer()->get(SettingsManager::class);
        $settingsManager->set('loyalty_program_active', '1');
        $settingsManager->flush();
    }

    private function createCustomer(): User
    {
        $userManager = self::getContainer()->get(UserManager::class);

        $user = $userManager->createUser();
        $this->customerUsername = 'loyalty_test_customer_' . uniqid();
        $user->setUsername($this->customerUsername);
        $user->setEmail($this->customerUsername . '@example.com');
        $user->setPlainPassword('irrelevant');
        $user->setRoles(['ROLE_USER']);
        $user->setEnabled(true);
        $userManager->updateUser($user);

        return $user;
    }

    private function createReward(int $pointsCost): LoyaltyReward
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $reward = new LoyaltyReward();
        $reward->setName('Free delivery');
        $reward->setPointsCost($pointsCost);
        $reward->setRewardType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
        $reward->setCouponValidityDays(30);
        $entityManager->persist($reward);
        $entityManager->flush();

        $this->rewardId = $reward->getId();

        return $reward;
    }

    private function credit(User $user, int $amount): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $entityManager->persist(
            LoyaltyPointsEntry::credit($user->getCustomer(), $amount, new \DateTime('+90 days'))
        );
        $entityManager->flush();
    }

    public function testPageShowsTheBalanceAndRewards(): void
    {
        $client = self::createClient();
        $this->activateProgram();

        $user = $this->createCustomer();
        $this->createReward(150);
        $this->credit($user, 200);

        $client->loginUser($user, 'web');
        $client->request('GET', '/profile/loyalty');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.stat-value', '200');
        self::assertSelectorTextContains('body', 'Free delivery');
    }

    public function testRedeemingSpendsThePointsAndIssuesACoupon(): void
    {
        $client = self::createClient();
        $this->activateProgram();

        $user = $this->createCustomer();
        $reward = $this->createReward(150);
        $this->credit($user, 200);

        $client->loginUser($user, 'web');
        $crawler = $client->request('GET', '/profile/loyalty');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', sprintf('/profile/loyalty/redeem/%d', $reward->getId()), ['_token' => $token]);

        self::assertResponseRedirects('/profile/loyalty');

        $client->followRedirect();

        // The app under test runs with COOPCYCLE_LOCALE=fr.
        self::assertSelectorTextContains('.flash-messages', 'Récompense utilisée');
        self::assertSelectorTextContains('.stat-value', '50');
    }

    public function testNoRedeemButtonIsOfferedBelowTheRewardCost(): void
    {
        $client = self::createClient();
        $this->activateProgram();

        $user = $this->createCustomer();
        $this->createReward(150);
        $this->credit($user, 10);

        $client->loginUser($user, 'web');
        $crawler = $client->request('GET', '/profile/loyalty');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action*="loyalty/redeem"]'));
    }

    /**
     * The button is gone once the points are, but a page loaded before they
     * went can still post -- that has to fail gracefully rather than
     * overspend.
     */
    public function testRedeemingFromAStalePageIsRefused(): void
    {
        $client = self::createClient();
        $this->activateProgram();

        $user = $this->createCustomer();
        $reward = $this->createReward(150);
        $this->credit($user, 200);

        $client->loginUser($user, 'web');
        $crawler = $client->request('GET', '/profile/loyalty');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        // The points go after the page was rendered, leaving the button stale.
        self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('UPDATE AppBundle\Entity\Loyalty\LoyaltyPointsEntry e SET e.remaining = 0 WHERE e.customer = :customer')
            ->setParameter('customer', $user->getCustomer())
            ->execute();

        $client->request('POST', sprintf('/profile/loyalty/redeem/%d', $reward->getId()), ['_token' => $token]);

        self::assertResponseRedirects('/profile/loyalty');
        $client->followRedirect();

        self::assertSelectorTextContains('.flash-messages', 'Il vous faut 150 point(s)');
    }

    public function testRedeemingRejectsAnInvalidCsrfToken(): void
    {
        $client = self::createClient();
        $this->activateProgram();

        $user = $this->createCustomer();
        $reward = $this->createReward(150);
        $this->credit($user, 200);

        $client->loginUser($user, 'web');
        $client->request('POST', sprintf('/profile/loyalty/redeem/%d', $reward->getId()), [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPageIsNotFoundWhileTheProgramIsInactive(): void
    {
        $client = self::createClient();

        $settingsManager = self::getContainer()->get(SettingsManager::class);
        $settingsManager->set('loyalty_program_active', '0');
        $settingsManager->flush();

        $user = $this->createCustomer();

        $client->loginUser($user, 'web');
        $client->request('GET', '/profile/loyalty');

        self::assertResponseStatusCodeSame(404);
    }
}
