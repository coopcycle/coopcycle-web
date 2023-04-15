<?php

namespace AppBundle\Sylius\Order;

use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class OrderModifier implements OrderModifierInterface
{
	public function __construct(
        OrderModifierInterface $orderModifier,
        OrderInvitationContext $context,
    	LoggerInterface $logger)
    {
        $this->orderModifier = $orderModifier;
        $this->context = $context;
        $this->logger = $logger;
    }

    public function addToOrder(OrderInterface $cart, OrderItemInterface $cartItem): void
    {
        if ($this->context->isPlayerOf($cart)) {
            $customer = $this->context->getCustomer();
            $cartItem->setCustomer($customer);
            $this->logger->debug("OrderModifier | adding item by {$customer->getEmail()}");
        }

        $this->orderModifier->addToOrder($cart, $cartItem);
    }

    public function removeFromOrder(OrderInterface $cart, OrderItemInterface $item): void
    {
        $this->orderModifier->removeFromOrder($cart, $item);
    }
}
