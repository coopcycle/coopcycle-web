<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class OrderFeeProcessor implements OrderProcessorInterface
{
    private $adjustmentFactory;
    private $translator;

    public function __construct(
        AdjustmentFactoryInterface $adjustmentFactory,
        TranslatorInterface $translator)
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        $restaurant = $order->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        $order->removeAdjustments(AdjustmentInterface::DELIVERY_ADJUSTMENT);
        $order->removeAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $feeRate = $restaurant->getContract()->getFeeRate();
        $customerAmount = $restaurant->getContract()->getCustomerAmount();
        $businessAmount = $restaurant->getContract()->getFlatDeliveryPrice();

        $deliveryPromotionAdjustments = $order->getAdjustments(AdjustmentInterface::DELIVERY_PROMOTION_ADJUSTMENT);
        foreach ($deliveryPromotionAdjustments as $deliveryPromotionAdjustment) {
            $businessAmount += $deliveryPromotionAdjustment->getAmount();
        }

        $feeAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::FEE_ADJUSTMENT,
            $this->translator->trans('order.adjustment_type.platform_fees'),
            (int) (($order->getItemsTotal() * $feeRate) + $businessAmount),
            $neutral = true
        );
        $order->addAdjustment($feeAdjustment);

        $deliveryAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::DELIVERY_ADJUSTMENT,
            $this->translator->trans('order.adjustment_type.delivery'),
            $customerAmount,
            $neutral = false
        );
        $order->addAdjustment($deliveryAdjustment);
    }
}
