<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Service\LoggingUtils;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;

final class OrderItemQuantityModifier implements OrderItemQuantityModifierInterface
{

    public function __construct(
        private OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    { }

    public function modify(OrderItemInterface $orderItem, int $targetQuantity): void
    {
        $order = $orderItem->getOrder();
        $this->checkoutLogger->info(sprintf('Order %s | OrderItemQuantityModifier | modifying %s | target quantity: %d | itemsTotal: %d (old)',
            $this->loggingUtils->getOrderId($order), $orderItem->getVariant()->getCode(), $targetQuantity, $order->getItemsTotal()));

        $this->orderItemQuantityModifier->modify($orderItem, $targetQuantity);

        $this->checkoutLogger->info(sprintf('Order %s | OrderItemQuantityModifier | modified %s | target quantity: %d | itemsTotal: %d (new)',
            $this->loggingUtils->getOrderId($order), $orderItem->getVariant()->getCode(), $targetQuantity, $order->getItemsTotal()));
    }
}
