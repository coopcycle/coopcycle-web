<?php

namespace AppBundle\Sylius\OrderProcessing;

use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Translation\TranslatorInterface;

final class OrderDepositRefundProcessor implements OrderProcessorInterface
{

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
        $order->removeAdjustmentsRecursively(AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT);

        if (!$order->getRestaurant()->getDepositRefundEnabled()) {
            return;
        }

        if (!$order->isReusablePackagingEnabled()) {
            return;
        }

        $totalUnits = 0;
        foreach ($order->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                $units = ceil($product->getReusablePackagingUnit() * $item->getQuantity());
                $label = $this->translator->trans('order_item.adjustment_type.reusable_packaging', [
                    '%quantity%' => $item->getQuantity()
                ]);

                $item->addAdjustment($this->adjustmentFactory->createWithData(
                    AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                    $label,
                    $units * 100,
                    $neutral = true
                ));

                $totalUnits += $units;
            }
        }

        $deliveryAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
            $this->translator->trans('order.adjustment_type.reusable_packaging'),
            $totalUnits * 100,
            $neutral = false
        );
        $order->addAdjustment($deliveryAdjustment);
    }
}
