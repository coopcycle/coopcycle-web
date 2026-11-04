<?php

namespace Tests\AppBundle\Service\Loyalty;

use AppBundle\Entity\Loyalty\LoyaltyPointsEntry;
use AppBundle\Entity\Loyalty\LoyaltyPointsEntryRepository;
use AppBundle\Entity\Loyalty\LoyaltyReward;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Service\Loyalty\InsufficientLoyaltyPointsException;
use AppBundle\Service\Loyalty\LoyaltyPointsManager;
use AppBundle\Service\Promotion\CustomerRewardCouponFactory;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

class LoyaltyPointsManagerTest extends TestCase
{
    use ProphecyTrait;

    private $pointsEntryRepository;
    private $settingsManager;
    private $couponFactory;
    private $entityManager;
    private $manager;

    protected function setUp(): void
    {
        $this->pointsEntryRepository = $this->prophesize(LoyaltyPointsEntryRepository::class);
        $this->pointsEntryRepository->findOneByOrder(Argument::any())->willReturn(null);

        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->settingsManager->get(Argument::any())->willReturn(null);

        $this->couponFactory = $this->prophesize(CustomerRewardCouponFactory::class);

        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        // Run the transaction body inline, so redeem() is exercised for real.
        // Prophecy rebinds $this inside will(), hence the explicit capture.
        $entityManager = $this->entityManager;
        $this->entityManager->wrapInTransaction(Argument::type('callable'))
            ->will(function ($args) use ($entityManager) {
                return $args[0]($entityManager->reveal());
            });
        $this->entityManager->persist(Argument::any())->willReturn(null);
        $this->entityManager->flush()->willReturn(null);

        $this->manager = new LoyaltyPointsManager(
            $this->pointsEntryRepository->reveal(),
            $this->settingsManager->reveal(),
            $this->couponFactory->reveal(),
            $this->entityManager->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal()
        );
    }

    private function reward(int $pointsCost): LoyaltyReward
    {
        $reward = new LoyaltyReward();
        $reward->setName('5 € off');
        $reward->setPointsCost($pointsCost);
        $reward->setRewardType(FixedDiscountPromotionActionCommand::TYPE);
        $reward->setRewardAmount(500);
        $reward->setCouponValidityDays(30);

        return $reward;
    }

    private function orderWorth(int $itemsTotal): Order
    {
        $customer = new Customer();

        $order = $this->prophesize(Order::class);
        $order->getCustomer()->willReturn($customer);
        $order->getItemsTotal()->willReturn($itemsTotal);
        $order->getId()->willReturn(1);

        return $order->reveal();
    }

    public function testEarnsOnePointPerCurrencyUnitByDefault(): void
    {
        // 23,50 € of food -> 23 points.
        self::assertSame(23, $this->manager->calculatePoints($this->orderWorth(2350)));
    }

    public function testEarnsAtTheConfiguredRate(): void
    {
        $this->settingsManager->get('loyalty_points_per_currency_unit')->willReturn(5);

        self::assertSame(117, $this->manager->calculatePoints($this->orderWorth(2350)));
    }

    /**
     * Rounding down rather than to nearest, so an order can never earn more
     * points than it was worth.
     */
    public function testDropsPartialPoints(): void
    {
        self::assertSame(9, $this->manager->calculatePoints($this->orderWorth(999)));
    }

    public function testCreditsTheOrderWithAnExpiryDate(): void
    {
        $this->settingsManager->get('loyalty_points_validity_days')->willReturn(30);

        $order = $this->orderWorth(2350);
        $entry = $this->manager->creditForOrder($order);

        self::assertInstanceOf(LoyaltyPointsEntry::class, $entry);
        self::assertTrue($entry->isCredit());
        self::assertSame(23, $entry->getAmount());
        self::assertSame(23, $entry->getRemaining());
        self::assertSame($order, $entry->getOrder());
        self::assertEqualsWithDelta(
            (new \DateTime('+30 days'))->getTimestamp(),
            $entry->getExpiresAt()->getTimestamp(),
            5
        );
    }

    public function testPointsNeverExpireWhenValidityIsZero(): void
    {
        $this->settingsManager->get('loyalty_points_validity_days')->willReturn(0);

        // 0 is not "expire immediately" -- SettingsManager returns null for
        // an unset setting, and 0 is the way to say "no expiry at all".
        self::assertNull($this->manager->creditForOrder($this->orderWorth(2350))->getExpiresAt());
    }

    public function testEarnsNothingOnAnOrderBelowOnePoint(): void
    {
        self::assertNull($this->manager->creditForOrder($this->orderWorth(99)));
    }

    /**
     * CheckoutSucceeded can be replayed, and paying out twice for one order
     * would be free points.
     */
    public function testDoesNotCreditAnOrderTwice(): void
    {
        $order = $this->orderWorth(2350);

        $this->pointsEntryRepository->findOneByOrder($order)
            ->willReturn(LoyaltyPointsEntry::credit(new Customer(), 23));

        self::assertNull($this->manager->creditForOrder($order));
    }

    public function testRedeemingSpendsTheOldestPointsFirst(): void
    {
        $customer = new Customer();
        $customer->setEmail('alice@example.com');

        // Expiring soonest first, as the repository returns them.
        $oldest = LoyaltyPointsEntry::credit($customer, 100, new \DateTime('+10 days'));
        $newer = LoyaltyPointsEntry::credit($customer, 200, new \DateTime('+90 days'));

        $this->pointsEntryRepository
            ->findSpendableCredits($customer, Argument::cetera())
            ->willReturn([$oldest, $newer]);

        $coupon = $this->prophesize(PromotionCouponInterface::class)->reveal();
        $this->couponFactory->create(Argument::cetera())->willReturn($coupon);

        $entry = $this->manager->redeem($customer, $this->reward(150));

        self::assertFalse($entry->isCredit());
        // Debits are negative, so a plain SUM() over the ledger reads as history.
        self::assertSame(-150, $entry->getAmount());
        self::assertSame($coupon, $entry->getCoupon());

        // The soonest-to-expire credit is drained before the fresher one is touched.
        self::assertSame(0, $oldest->getRemaining());
        self::assertSame(150, $newer->getRemaining());
    }

    public function testRedeemingRefusesWhenPointsAreShort(): void
    {
        $customer = new Customer();

        $this->pointsEntryRepository
            ->findSpendableCredits($customer, Argument::cetera())
            ->willReturn([LoyaltyPointsEntry::credit($customer, 100)]);

        $this->couponFactory->create(Argument::cetera())->shouldNotBeCalled();
        $this->entityManager->persist(Argument::any())->shouldNotBeCalled();

        $this->expectException(InsufficientLoyaltyPointsException::class);

        $this->manager->redeem($customer, $this->reward(150));
    }

    /**
     * Expired credits are already excluded by the repository, so a balance
     * made up entirely of them can't be spent.
     */
    public function testRedeemingRefusesWhenThereAreNoSpendableCredits(): void
    {
        $customer = new Customer();

        $this->pointsEntryRepository
            ->findSpendableCredits($customer, Argument::cetera())
            ->willReturn([]);

        $this->expectException(InsufficientLoyaltyPointsException::class);

        $this->manager->redeem($customer, $this->reward(1));
    }
}
