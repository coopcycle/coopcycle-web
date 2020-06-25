<?php

namespace AppBundle\Sylius\Promotion\Modifier;

use Sylius\Component\Order\Model\OrderInterface;

/**
 * @see https://github.com/Sylius/Sylius/blob/60134b783c51c4b9da0b58c5a54482824a8baf8a/src/Sylius/Component/Core/Promotion/Modifier/OrderPromotionsUsageModifier.php
 */
final class OrderPromotionsUsageModifier
{
    /**
     * {@inheritdoc}
     */
    public function increment(OrderInterface $order): void
    {
        foreach ($order->getPromotions() as $promotion) {
            $promotion->incrementUsed();
        }

        $promotionCoupon = $order->getPromotionCoupon();
        if (null !== $promotionCoupon) {
            $promotionCoupon->incrementUsed();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(OrderInterface $order): void
    {
        foreach ($order->getPromotions() as $promotion) {
            $promotion->decrementUsed();
        }

        $promotionCoupon = $order->getPromotionCoupon();
        if (null !== $promotionCoupon) {
            $promotionCoupon->decrementUsed();
        }
    }
}
