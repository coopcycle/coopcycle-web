<?php

namespace AppBundle\LoopEat;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GuestCheckoutAwareAdapter implements OAuthCredentialsInterface
{
    public function __construct(OrderInterface $order)
    {
        $this->order = $order;
    }

    public function getLoopeatAccessToken()
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->getLoopeatAccessToken();
        }

        return $this->order->getLoopeatAccessToken();
    }

    public function setLoopeatAccessToken($accessToken)
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->setLoopeatAccessToken($accessToken);
        }

        $this->order->setLoopeatAccessToken($accessToken);
    }

    public function getLoopeatRefreshToken()
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->getLoopeatRefreshToken();
        }

        return $this->order->getLoopeatRefreshToken();
    }

    public function setLoopeatRefreshToken($refreshToken)
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->setLoopeatRefreshToken($refreshToken);
        }

        $this->order->setLoopeatRefreshToken($refreshToken);
    }

    public function hasLoopEatCredentials(): bool
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->hasLoopEatCredentials();
        }

        return $this->order->hasLoopEatCredentials();
    }

    public function clearLoopEatCredentials()
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
