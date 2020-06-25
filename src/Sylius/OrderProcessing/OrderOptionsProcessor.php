<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final class OrderOptionsProcessor implements OrderProcessorInterface
{
    private $adjustmentFactory;
    private $logger;

    public function __construct(AdjustmentFactoryInterface $adjustmentFactory, LoggerInterface $logger = null)
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->logger = $logger ? $logger : new NullLogger();
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

                try {

                    $option = $optionValue->getOption();
                    $quantity = $variant->getQuantityForOptionValue($optionValue);

                    $amount = 0;
                    switch ($option->getStrategy()) {
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

                } catch (EntityNotFoundException $e) {
                    // This happens when an option has been deleted,
                    // but is still attached to a product variant
                    $this->logger->error($e->getMessage(), ['exception' => $e]);
                }
            }
        }
    }
}
