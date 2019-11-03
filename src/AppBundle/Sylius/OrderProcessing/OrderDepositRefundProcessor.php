<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Partner\LoopEat\Client as LoopEatClient;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Translation\TranslatorInterface;

final class OrderDepositRefundProcessor implements OrderProcessorInterface
{
    public function __construct(
        AdjustmentFactoryInterface $adjustmentFactory,
        TranslatorInterface $translator,
        LoopEatClient $loopeatClient
    )
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->translator = $translator;
        $this->loopeatClient = $loopeatClient;
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
            if (!$restaurant->getDepositRefundEnabled()) {
                return;
            }
            if (!$order->isReusablePackagingEnabled()) {
                return;
            }
        }

        $loopeatPrice = 1000;
        if ($restaurant->isLoopeatEnabled()) {
            $loopeatPrice = $this->loopeatClient->getPrice();
        }

        $totalUnits = 0;
        foreach ($order->getItems() as $item) {

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                $units = ceil($product->getReusablePackagingUnit() * $item->getQuantity());

                if ($restaurant->isLoopeatEnabled()) {

                    $item->addAdjustment($this->adjustmentFactory->createWithData(
                        AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                        sprintf('%d × LoopEat(s)', $item->getQuantity()),
                        $loopeatPrice * $units,
                        $neutral = true
                    ));

                } else {

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
                }

                $totalUnits += $units;
            }
        }

        if ($restaurant->isLoopeatEnabled()) {
            $order->addAdjustment($this->adjustmentFactory->createWithData(
                AdjustmentInterface::REUSABLE_PACKAGING_ADJUSTMENT,
                $this->translator->trans('order.adjustment_type.reusable_packaging'),
                $loopeatPrice * $totalUnits,
                $neutral = false
            ));
        } else {
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
}
