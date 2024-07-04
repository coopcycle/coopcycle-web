<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final class OrderOptionsProcessor implements OrderProcessorInterface
{
    private $adjustmentFactory;

    public function __construct(
        AdjustmentFactoryInterface $adjustmentFactory,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    {
        $this->adjustmentFactory = $adjustmentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->checkoutLogger->info(sprintf('OrderOptionsProcessor | started | itemsTotal: %d (initial)', $order->getItemsTotal()),
            ['order' => $this->loggingUtils->getOrderId($order)]);

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
                    $this->checkoutLogger->warning(sprintf('OrderOptionsProcessor | error: %s', $e->getMessage()),
                        ['order' => $this->loggingUtils->getOrderId($order), 'exception' => $e]);
                }
            }
        }

        $this->checkoutLogger->info(sprintf('OrderOptionsProcessor | finished | itemsTotal: %d (updated)', $order->getItemsTotal()),
            ['order' => $this->loggingUtils->getOrderId($order)]);
    }
}
