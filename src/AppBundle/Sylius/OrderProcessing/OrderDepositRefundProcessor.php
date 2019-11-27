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

        $restaurant = $order->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        if ($restaurant->isDepositRefundOptin()) {
            if (!$restaurant->isDepositRefundEnabled()) {
                return;
            }
            if (!$order->isReusablePackagingEnabled()) {
                return;
            }
        }

        $totalUnits = 0;
        foreach ($order->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                $units = ceil($product->getReusablePackagingUnit() * $item->getQuantity());
                $label = $this->translator->trans('order_item.adjustment_type.reusable_packaging', [
                    '%quantity%' => $item->getQuantity()
                ]);

                foreach ($restaurant->getReusablePackagings() as $reusablePackaging) {
                    $item->addAdjustment($this->adjustmentFactory->createWithData(
                        AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                        $label,
                        $reusablePackaging->getPrice() * $units,
                        $neutral = true
                    ));
                }

                $totalUnits += $units;
            }
        }

        foreach ($restaurant->getReusablePackagings() as $reusablePackaging) {
            $order->addAdjustment($this->adjustmentFactory->createWithData(
                AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.reusable_packaging'),
                $reusablePackaging->getPrice() * $totalUnits,
                $neutral = false
            ));
        }
    }
}
