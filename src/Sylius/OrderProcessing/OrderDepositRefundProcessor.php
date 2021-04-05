<?php

namespace AppBundle\Sylius\OrderProcessing;

use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Model\Adjustment;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

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

        // For the moment, not supported on hubs
        if (!$order->hasVendor() || $order->isMultiVendor()) {
            return;
        }

        $restaurant = $order->getVendor()->getRestaurant();

        if ($restaurant->isDepositRefundOptin()) {
            if (!$restaurant->isDepositRefundEnabled() && !$restaurant->isLoopeatEnabled()) {
                return;
            }
            if (!$order->isReusablePackagingEnabled()) {
                return;
            }
        }

        $totalAmount = 0;
        foreach ($order->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                $reusablePackaging = $product->getReusablePackaging();

                if (null === $reusablePackaging) {
                    continue;
                }

                $units = ceil($product->getReusablePackagingUnit() * $item->getQuantity());
                $label = $this->translator->trans('order_item.adjustment_type.reusable_packaging', [
                    '%quantity%' => ceil($product->getReusablePackagingUnit() * $item->getQuantity())
                ]);

                $amount = $reusablePackaging->getPrice() * $units;

                $item->addAdjustment($this->adjustmentFactory->createWithData(
                    AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                    $label,
                    $amount,
                    $neutral = true
                ));

                $totalAmount += $amount;
            }
        }

        if ($totalAmount > 0) {
            $order->addAdjustment($this->adjustmentFactory->createWithData(
                AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.reusable_packaging'),
                $totalAmount,
                $neutral = false
            ));
        }
    }
}
