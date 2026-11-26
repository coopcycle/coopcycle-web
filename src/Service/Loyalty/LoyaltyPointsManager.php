<?php

namespace AppBundle\Service\Loyalty;

use AppBundle\Entity\Loyalty\LoyaltyPointsEntry;
use AppBundle\Entity\Loyalty\LoyaltyPointsEntryRepository;
use AppBundle\Entity\Loyalty\LoyaltyReward;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Service\Promotion\CustomerRewardCouponFactory;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\OrderInterface;

/**
 * Owns the points ledger: what an order earns, and what a customer has left
 * to spend.
 */
class LoyaltyPointsManager
{
    public const DEFAULT_POINTS_PER_CURRENCY_UNIT = 1;
    public const DEFAULT_POINTS_VALIDITY_DAYS = 365;

    public function __construct(
        private readonly LoyaltyPointsEntryRepository $pointsEntryRepository,
        private readonly SettingsManager $settingsManager,
        private readonly CustomerRewardCouponFactory $customerRewardCouponFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getBalance(Customer $customer): int
    {
        return $this->pointsEntryRepository->getBalance($customer);
    }

    /**
     * Spends the reward's cost and hands back the debit entry, which carries
     * the coupon that was minted for it.
     *
     * The whole thing runs in one transaction with a write lock on the
     * credits being spent: without it, two redemptions racing each other
     * would both read the same balance and both succeed, handing out a
     * coupon the customer hadn't paid for.
     *
     * @throws InsufficientLoyaltyPointsException
     */
    public function redeem(Customer $customer, LoyaltyReward $reward): LoyaltyPointsEntry
    {
        return $this->entityManager->wrapInTransaction(function () use ($customer, $reward) {
            $credits = $this->pointsEntryRepository->findSpendableCredits(
                $customer,
                lockMode: LockMode::PESSIMISTIC_WRITE
            );

            $available = array_sum(array_map(fn (LoyaltyPointsEntry $credit) => $credit->getRemaining(), $credits));
            $pointsCost = $reward->getPointsCost();

            if ($available < $pointsCost) {
                throw new InsufficientLoyaltyPointsException($available, $pointsCost);
            }

            $leftToSpend = $pointsCost;
            foreach ($credits as $credit) {
                if ($leftToSpend < 1) {
                    break;
                }
                $leftToSpend -= $credit->consume($leftToSpend);
            }

            $coupon = $this->customerRewardCouponFactory->create(
                name: sprintf('Loyalty reward - %s', $customer->getUsername()),
                customer: $customer,
                rewardType: $reward->getRewardType(),
                amount: $reward->getRewardAmount(),
                percentage: $reward->getRewardPercentage(),
                validityDays: $reward->getCouponValidityDays(),
                usageLimit: 1,
            );

            $entry = LoyaltyPointsEntry::debit($customer, $pointsCost);
            $entry->setReward($reward);
            $entry->setCoupon($coupon);

            $this->entityManager->persist($entry);
            $this->entityManager->flush();

            $this->logger->info(sprintf(
                'Customer #%d redeemed %d point(s) for reward "%s"',
                $customer->getId(),
                $pointsCost,
                $reward->getName()
            ));

            return $entry;
        });
    }

    /**
     * Points are earned on the items total *net of order-level discounts*:
     * the food, excluding the delivery fee (an order-level adjustment, not
     * part of this), so a longer -- and for the co-op, costlier -- delivery
     * doesn't earn more than a short one.
     *
     * Returns null when the order earns nothing, which is also the case for
     * an order that has already been credited: the entry holds the order it
     * came from, so a replayed OrderFulfilled can't pay out twice.
     */
    public function creditForOrder(OrderInterface $order): ?LoyaltyPointsEntry
    {
        $customer = $order->getCustomer();

        if (!$customer instanceof Customer) {
            return null;
        }

        if (null !== $this->pointsEntryRepository->findOneByOrder($order)) {
            $this->logger->info(sprintf(
                'Order #%s has already earned loyalty points, skipping',
                $order->getId()
            ));

            return null;
        }

        $points = $this->calculatePoints($order);

        if ($points < 1) {
            return null;
        }

        $entry = LoyaltyPointsEntry::credit($customer, $points, $this->calculateExpiryDate());
        $entry->setOrder($order);

        return $entry;
    }

    public function calculatePoints(OrderInterface $order): int
    {
        $pointsPerCurrencyUnit = $this->getIntSetting(
            'loyalty_points_per_currency_unit',
            self::DEFAULT_POINTS_PER_CURRENCY_UNIT
        );

        // Totals are in cents, and partial points are dropped rather than
        // rounded, so an order can never earn more than it was worth.
        return intdiv($this->getEarningBase($order) * $pointsPerCurrencyUnit, 100);
    }

    /**
     * What the customer actually paid for the food.
     *
     * getItemsTotal() is the undiscounted sum of the items: promotions --
     * manual coupons, referral rewards, and the reward coupons this very
     * program hands out -- are ORDER_PROMOTION_ADJUSTMENT rows added to the
     * *order*, not to the items (see FixedDiscount/PercentageDiscount
     * PromotionActionCommand), so they never show up there. Earning on the
     * gross total meant a EUR 20 order paid EUR 15 with a EUR 5 coupon still
     * earned 20 points, stacking the discount with the points it didn't buy
     * -- and, with a loyalty reward coupon, partly refunding the points just
     * spent on it.
     *
     * Delivery promotions are deliberately left out: the delivery fee isn't
     * in getItemsTotal() either, so subtracting a discount on it would take
     * away points for something that never earned any.
     *
     * Adjustment amounts are negative, hence the addition; clamped at 0
     * because a fixed discount can be larger than the items it applies to.
     */
    private function getEarningBase(OrderInterface $order): int
    {
        $discount = $order->getAdjustmentsTotal(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);

        return max(0, $order->getItemsTotal() + $discount);
    }

    private function calculateExpiryDate(): ?\DateTime
    {
        $validityDays = $this->getIntSetting(
            'loyalty_points_validity_days',
            self::DEFAULT_POINTS_VALIDITY_DAYS
        );

        // 0 is how an admin says "points never expire"; it's distinct from
        // the setting being unset, which falls back to the default above.
        if ($validityDays < 1) {
            return null;
        }

        return new \DateTime(sprintf('+%d days', $validityDays));
    }

    private function getIntSetting(string $name, int $default): int
    {
        $value = $this->settingsManager->get($name);

        return null === $value || '' === $value ? $default : (int) $value;
    }
}
