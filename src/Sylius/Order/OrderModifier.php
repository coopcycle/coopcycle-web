<?php

namespace AppBundle\Sylius\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
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
        IriConverterInterface $iriConverter,
    	LoggerInterface $logger)
    {
        $this->orderModifier = $orderModifier;
        $this->requestStack = $requestStack;
        $this->iriConverter = $iriConverter;
        $this->logger = $logger;
    }

    public function addToOrder(OrderInterface $cart, OrderItemInterface $cartItem): void
    {
        $session = $this->requestStack->getSession();

        if ($session->has('guest_customer')) {
            $customer = $this->iriConverter->getItemFromIri($session->get('guest_customer'));
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
