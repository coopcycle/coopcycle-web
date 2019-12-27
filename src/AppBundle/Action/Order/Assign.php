<?php

namespace AppBundle\Action\Order;

use AppBundle\Service\OrderManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class Assign
{
    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke($data)
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return $data;
        }

        if (!$token->hasAttribute('cart')) {
            // var_dump($token->getAttribute('cart')->getId());
            // if ($token->getAttribute('cart'))

            return $data;
        }

        $cart = $token->getAttribute('cart');

        if ($cart && $data !== $cart) {
            throw new AccessDeniedException();
        }

        var_dump($data->getId());
        var_dump($token->getAttribute('cart')->getId());
        // var_dump($token->hasAttribute('cart'));

        if (is_object($user = $token->getUser())) {
            $data->setCustomer($user);
        }

        return $data;
    }
}
