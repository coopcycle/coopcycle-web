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

            return $data;
        }

        $cart = $token->getAttribute('cart');

        if ($cart && $data !== $cart) {
            throw new AccessDeniedException();
        }

        if (is_object($user = $token->getUser())) {
            $data->setCustomer($user->getCustomer());
        }

        return $data;
    }
}
