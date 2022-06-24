<?php

namespace AppBundle\LoopEat;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GuestCheckoutAwareAdapter implements OAuthCredentialsInterface
{
    public function __construct(OrderInterface $order, SessionInterface $session)
    {
        $this->order = $order;
        $this->session = $session;
    }

    public function getLoopeatAccessToken()
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->getLoopeatAccessToken();
        }

        return $this->session->get(sprintf('loopeat.order.%d.access_token', $this->order->getId()));
    }

    public function setLoopeatAccessToken($accessToken)
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->setLoopeatAccessToken($accessToken);
        }

        $this->session->set(
            sprintf('loopeat.order.%d.access_token', $this->order->getId()),
            $accessToken
        );
    }

    public function getLoopeatRefreshToken()
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->getLoopeatRefreshToken();
        }

        return $this->session->get(sprintf('loopeat.order.%d.refresh_token', $this->order->getId()));
    }

    public function setLoopeatRefreshToken($refreshToken)
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->setLoopeatRefreshToken($refreshToken);
        }

        $this->session->set(
            sprintf('loopeat.order.%d.refresh_token', $this->order->getId()),
            $refreshToken
        );
    }

    public function hasLoopEatCredentials(): bool
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->hasLoopEatCredentials();
        }

        return $this->session->has(sprintf('loopeat.order.%d.access_token', $this->order->getId()))
            && $this->session->has(sprintf('loopeat.order.%d.refresh_token', $this->order->getId()));
    }

    public function clearLoopEatCredentials()
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
