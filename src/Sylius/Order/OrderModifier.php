<?php

namespace AppBundle\Sylius\Order;

use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class OrderModifier implements OrderModifierInterface
{
	public function __construct(
        OrderModifierInterface $orderModifier,
        RequestStack $requestStack,
    	LoggerInterface $logger)
    {
        $this->orderModifier = $orderModifier;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    public function addToOrder(OrderInterface $cart, OrderItemInterface $cartItem): void
    {
    	$this->orderModifier->addToOrder($cart, $cartItem);

        $guestCustomerEmail = $this->requestStack->getSession()->get('guest_customer_email');

    	$this->logger->debug("OrderModifier | adding item by {$guestCustomerEmail}");
    }

    public function removeFromOrder(OrderInterface $cart, OrderItemInterface $item): void
    {
        $this->orderModifier->removeFromOrder($cart, $item);
    }
}
