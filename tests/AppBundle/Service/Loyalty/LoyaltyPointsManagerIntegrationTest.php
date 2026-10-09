<?php

namespace Tests\AppBundle\Service\Loyalty;

use AppBundle\Entity\Loyalty\LoyaltyPointsEntry;
use AppBundle\Entity\Loyalty\LoyaltyReward;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Service\Loyalty\InsufficientLoyaltyPointsException;
use AppBundle\Service\Loyalty\LoyaltyPointsManager;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Covers what the unit tests can't: that redeeming really does run in a
 * transaction, take its lock, spend the right rows and mint a coupon against
 * the actual database.
 */
class LoyaltyPointsManagerIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?Customer $customer = null;
    private ?LoyaltyReward $reward = null;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->customer = new Customer();
        $this->customer->setEmail('loyalty_test_' . uniqid() . '@example.com');
        $this->customer->setEmailCanonical($this->customer->getEmail());
        $this->entityManager->persist($this->customer);

        $this->reward = new LoyaltyReward();
        $this->reward->setName('Free delivery');
        $this->reward->setPointsCost(150);
        $this->reward->setRewardType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
        $this->reward->setCouponValidityDays(30);
        $this->entityManager->persist($this->reward);

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        if (null !== $this->customer && null !== $this->customer->getId()) {
            $this->entityManager->createQuery('DELETE AppBundle\Entity\Loyalty\LoyaltyPointsEntry e WHERE e.customer = :customer')
                ->setParameter('customer', $this->customer)
                ->execute();
            $this->entityManager->createQuery('DELETE AppBundle\Entity\Sylius\Customer c WHERE c = :customer')
                ->setParameter('customer', $this->customer)
                ->execute();
        }

        if (null !== $this->reward && null !== $this->reward->getId()) {
            $this->entityManager->createQuery('DELETE AppBundle\Entity\Loyalty\LoyaltyReward r WHERE r = :reward')
                ->setParameter('reward', $this->reward)
                ->execute();
        }

        parent::tearDown();
    }

    private function credit(int $amount, string $expiresAt): LoyaltyPointsEntry
    {
        $entry = LoyaltyPointsEntry::credit($this->customer, $amount, new \DateTime($expiresAt));
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    public function testRedeemingSpendsTheSoonestToExpireCreditsAndIssuesACoupon(): void
    {
        $manager = self::getContainer()->get(LoyaltyPointsManager::class);

        $expiringSoon = $this->credit(100, '+10 days');
        $expiringLater = $this->credit(200, '+90 days');

        self::assertSame(300, $manager->getBalance($this->customer));

        $debit = $manager->redeem($this->customer, $this->reward);

        self::assertSame(-150, $debit->getAmount());
        self::assertNotNull($debit->getCoupon());
        self::assertTrue($debit->getCoupon()->isInternal());
        self::assertSame($this->reward->getId(), $debit->getReward()->getId());

        self::assertSame(0, $expiringSoon->getRemaining());
        self::assertSame(150, $expiringLater->getRemaining());
        self::assertSame(150, $manager->getBalance($this->customer));
    }

    public function testRedeemingRefusesOnceThePointsAreGone(): void
    {
        $manager = self::getContainer()->get(LoyaltyPointsManager::class);

        $this->credit(150, '+90 days');

        $manager->redeem($this->customer, $this->reward);
        self::assertSame(0, $manager->getBalance($this->customer));

        $this->expectException(InsufficientLoyaltyPointsException::class);
        $manager->redeem($this->customer, $this->reward);
    }

    /**
     * Expired credits are excluded from the balance rather than left to be
     * spent -- they're still on the ledger, just not spendable.
     */
    public function testExpiredCreditsCannotBeSpent(): void
    {
        $manager = self::getContainer()->get(LoyaltyPointsManager::class);

        $this->credit(500, '-1 day');

        self::assertSame(0, $manager->getBalance($this->customer));

        $this->expectException(InsufficientLoyaltyPointsException::class);
        $manager->redeem($this->customer, $this->reward);
    }
}
