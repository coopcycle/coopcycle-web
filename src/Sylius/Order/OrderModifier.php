<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\User;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class OrderModifier implements OrderModifierInterface
{
	public function __construct(
        private OrderModifierInterface $orderModifier,
        private OrderInvitationContext $context,
        private TokenStorageInterface $tokenStorage,
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
                !is_null($token = $this->tokenStorage->getToken()) &&
                !is_null($user = $token->getUser()) &&
                // Make sure it doesn't break when authenticated with OAuth
                // because we have an instance of League\Bundle\OAuth2ServerBundle\Security\User\NullUser
                $user instanceof User
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
