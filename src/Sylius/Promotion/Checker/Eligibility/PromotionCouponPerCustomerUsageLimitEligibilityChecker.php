<?php

namespace AppBundle\Sylius\Promotion\Checker\Eligibility;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Promotion\Checker\Eligibility\PromotionCouponEligibilityCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface as BasePromotionCouponInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;

final class PromotionCouponPerCustomerUsageLimitEligibilityChecker implements PromotionCouponEligibilityCheckerInterface
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function isEligible(PromotionSubjectInterface $promotionSubject, BasePromotionCouponInterface $promotionCoupon): bool
    {
        if (!$promotionSubject instanceof OrderInterface) {
            return true;
        }

        if (!$promotionCoupon instanceof PromotionCouponInterface) {
            return true;
        }

        $perCustomerUsageLimit = $promotionCoupon->getPerCustomerUsageLimit();
        if ($perCustomerUsageLimit === null) {
            return true;
        }

        $customer = $promotionSubject->getCustomer();
        if ($customer === null || $customer->getId() === null) {
            return true;
        }

        $placedOrdersNumber = $this->orderRepository->countByCustomerAndCoupon($customer, $promotionCoupon);

        return $placedOrdersNumber < $perCustomerUsageLimit;
    }
}
