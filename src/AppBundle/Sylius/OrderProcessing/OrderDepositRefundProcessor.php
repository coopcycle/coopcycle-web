<?php

namespace AppBundle\Sylius\OrderProcessing;

use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

final class OrderDepositRefundProcessor implements OrderProcessorInterface
{

    public function __construct(AdjustmentFactoryInterface $adjustmentFactory)
    {
        $this->adjustmentFactory = $adjustmentFactory;
    }
    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        $order->removeAdjustments(AdjustmentInterface::ORDER_DEPOSIT_ADJUSTMENT);

        if (!$order->getRestaurant()->getDepositRefundEnabled()) {
            return;
        }

        if (!$order->getReusablePackagingEnabled()){
            return;
        }


        $totalUnits = 0;
        foreach ($order->getItems() as $item) {
            if ($item->getVariant()->getProduct()->getReusablePackagingEnabled()) {
                $totalUnits += $item->getVariant()->getProduct()->getReusablePackagingUnit();
            }
        }



        $deliveryAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::ORDER_DEPOSIT_ADJUSTMENT,
            // $this->translator->trans('order.adjustment_type.delivery'),
            'consigne',
            $totalUnits*100,
            $neutral = false
        );
        $order->addAdjustment($deliveryAdjustment);
    }
}
