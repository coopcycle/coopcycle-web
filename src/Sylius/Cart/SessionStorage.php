<?php

namespace AppBundle\Sylius\Cart;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SessionStorage
{
    public function __construct(
        private RequestStack $requestStack,
        private OrderRepositoryInterface $orderRepository,
        private string $sessionKeyName)
    {}

    public function has(): bool
    {
        return $this->requestStack->getSession()->has($this->getSessionKey());
    }

    public function get(): ?OrderInterface
    {
        if ($this->has()) {
            $cartId = $this->requestStack->getSession()->get($this->getSessionKey());

            return $this->orderRepository->findCartById($cartId);
        }

        return null;
    }

    public function set(OrderInterface $cart)
    {
        $this->requestStack->getSession()->set($this->getSessionKey(), $cart->getId());
    }

    public function remove()
    {
        $this->requestStack->getSession()->remove($this->getSessionKey());
    }

    private function getSessionKey(): string
    {
        return $this->sessionKeyName;
    }
}

