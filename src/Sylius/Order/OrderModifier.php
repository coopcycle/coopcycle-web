<?php

namespace AppBundle\Sylius\Order;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;

final class OrderModifier implements OrderModifierInterface
{
	public function __construct(
        private OrderModifierInterface $orderModifier,
        private OrderInvitationContext $context,
        private ContainerInterface $container,
    	private LoggerInterface $logger)
    { }

    /**
     * @throws \Exception
     */
    public function addToOrder(OrderInterface $cart, OrderItemInterface $cartItem): void
    {
        if ($this->context->isPlayerOf($cart)) {
            $customer = $this->context->getCustomer();
            $cartItem->setCustomer($customer);
            $this->logger->debug("OrderModifier | adding item by {$customer->getEmail()}");
        } else {
                if (
                    !is_null($token = $this->container->get('security.token_storage')->getToken()) &&
                    !is_null($user = $token->getUser())
                ) {
                    $cartItem->setCustomer($user->getCustomer());
                }
        }

        $this->orderModifier->addToOrder($cart, $cartItem);
    }

    public function removeFromOrder(OrderInterface $cart, OrderItemInterface $item): void
    {
        $this->orderModifier->removeFromOrder($cart, $item);
    }
}
