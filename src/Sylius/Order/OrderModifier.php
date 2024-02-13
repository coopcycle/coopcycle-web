<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\User;
use AppBundle\Service\LoggingUtils;
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
    	private LoggerInterface $logger,
        private LoggerInterface $checkoutLogger,
        private LoggingUtils $loggingUtils)
    { }

    /**
     * @throws \Exception
     */
    public function addToOrder(OrderInterface $cart, OrderItemInterface $cartItem): void
    {
        $this->checkoutLogger->info(sprintf('Order %s | OrderModifier | adding %s | itemsTotal: %d (old)',
            $this->loggingUtils->getOrderId($cart), $cartItem->getVariant()->getCode(), $cart->getItemsTotal()));

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

        $this->checkoutLogger->info(sprintf('Order %s | OrderModifier | added %s | itemsTotal: %d (new)',
            $this->loggingUtils->getOrderId($cart), $cartItem->getVariant()->getCode(), $cart->getItemsTotal()));
    }

    public function removeFromOrder(OrderInterface $cart, OrderItemInterface $item): void
    {
        $this->checkoutLogger->info(sprintf('Order %s | OrderModifier | removing %s | itemsTotal: %d (old)',
            $this->loggingUtils->getOrderId($cart), $item->getVariant()->getCode(), $cart->getItemsTotal()));

        $this->orderModifier->removeFromOrder($cart, $item);

        $this->checkoutLogger->info(sprintf('Order %s | OrderModifier | removed %s | itemsTotal: %d (new)',
            $this->loggingUtils->getOrderId($cart), $item->getVariant()->getCode(), $cart->getItemsTotal()));
    }
}
