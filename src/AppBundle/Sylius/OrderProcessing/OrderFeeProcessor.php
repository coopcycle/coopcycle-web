<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final class OrderFeeProcessor implements OrderProcessorInterface
{
    private $adjustmentFactory;

    public function __construct(AdjustmentFactoryInterface $adjustmentFactory)
    {
        $this->adjustmentFactory = $adjustmentFactory;
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

        $order->removeAdjustments(AdjustmentInterface::FEE_ADJUSTMENT);

        $feeRate = $restaurant->getContract()->getFeeRate();

        $feeAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::FEE_ADJUSTMENT,
            'CoopCycle fees',
            (int) $order->getItemsTotal() * $feeRate,
            $neutral = true
        );

        $order->addAdjustment($feeAdjustment);
    }
}
