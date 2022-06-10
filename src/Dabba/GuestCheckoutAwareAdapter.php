<?php

namespace AppBundle\Dabba;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GuestCheckoutAwareAdapter implements OAuthCredentialsInterface
{
    public function __construct(OrderInterface $order, SessionInterface $session)
    {
        $this->order = $order;
        $this->session = $session;
    }

    public function getDabbaAccessToken()
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->getDabbaAccessToken();
        }

        return $this->session->get(sprintf('dabba.order.%d.access_token', $this->order->getId()));
    }

    public function setDabbaAccessToken($accessToken)
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->setDabbaAccessToken($accessToken);
        }

        $this->session->set(
            sprintf('dabba.order.%d.access_token', $this->order->getId()),
            $accessToken
        );
    }

    public function getDabbaRefreshToken()
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->getDabbaRefreshToken();
        }

        return $this->session->get(sprintf('dabba.order.%d.refresh_token', $this->order->getId()));
    }

    public function setDabbaRefreshToken($refreshToken)
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->setDabbaRefreshToken($refreshToken);
        }

        $this->session->set(
            sprintf('dabba.order.%d.refresh_token', $this->order->getId()),
            $refreshToken
        );
    }

    public function hasDabbaCredentials(): bool
    {
        if (null !== $this->order->getCustomer()) {

            return $this->order->getCustomer()->hasDabbaCredentials();
        }

        return $this->session->has(sprintf('dabba.order.%d.access_token', $this->order->getId()))
            && $this->session->has(sprintf('dabba.order.%d.refresh_token', $this->order->getId()));
    }

    public function clearDabbaCredentials()
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
