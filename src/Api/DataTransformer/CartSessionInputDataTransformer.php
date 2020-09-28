<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Api\Resource\CartSession;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CartSessionInputDataTransformer implements DataTransformerInterface
{
    public function __construct(
        FactoryInterface $orderFactory,
        TokenStorageInterface $tokenStorage)
    {
        $this->orderFactory = $orderFactory;
        $this->tokenStorage = $tokenStorage;
    }

    private function getCartFromSession()
    {
        if (null !== $token = $this->tokenStorage->getToken()) {
            if ($token instanceof JWTUserToken && $token->hasAttribute('cart')) {
                return $token->getAttribute('cart');
            }
        }
    }

    private function getUserFromToken()
    {
        if (null !== $token = $this->tokenStorage->getToken()) {
            if ($token instanceof JWTUserToken && is_object($token->getUser())) {
                return $token->getUser();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $session = new CartSession();

        if ($cart = $this->getCartFromSession()) {
            $cart->setRestaurant($data->restaurant);
        } else {
            $cart = $this->orderFactory->createForRestaurant($data->restaurant);
        }

        if (null === $cart->getCustomer() && $user = $this->getUserFromToken()) {
            $cart->setCustomer($user->getCustomer());
        }

        $session->cart = $cart;

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof CartSession) {
          return false;
        }

        return CartSession::class === $to && null !== ($context['input']['class'] ?? null);
    }
}
