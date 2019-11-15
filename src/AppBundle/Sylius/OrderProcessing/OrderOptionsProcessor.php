<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final class OrderOptionsProcessor implements OrderProcessorInterface
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

        foreach ($order->getItems() as $orderItem) {

            $orderItem->removeAdjustments(AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT);

            $variant = $orderItem->getVariant();

            foreach ($variant->getOptionValues() as $optionValue) {

                $option = $optionValue->getOption();
                $quantity = $variant->getQuantityForOptionValue($optionValue);

                $amount = 0;
                switch ($option->getStrategy()) {
                    case ProductOptionInterface::STRATEGY_OPTION:
                        $amount = ($option->getPrice() * $quantity) * $orderItem->getQuantity();
                        break;
                    case ProductOptionInterface::STRATEGY_OPTION_VALUE:
                        $amount = ($optionValue->getPrice() * $quantity) * $orderItem->getQuantity();
                        break;
                }

                $adjustment = $this->adjustmentFactory->createWithData(
                    AdjustmentInterface::MENU_ITEM_MODIFIER_ADJUSTMENT,
                    sprintf('%d × %s', $quantity, $optionValue->getValue()),
                    $amount,
                    $neutral = false
                );

                $orderItem->addAdjustment($adjustment);
            }
        }
    }
}
