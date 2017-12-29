<?php

namespace AppBundle\Service;

use AppBundle\Entity\Cart\Cart;
use Symfony\Component\HttpFoundation\RequestStack;

class CartProviderService
{
    public function __construct($doctrine, RequestStack $requestStack)
    {
        $this->doctrine = $doctrine;
        $this->requestStack = $requestStack;

    }

    public function getCart() {
        $request = $this->requestStack->getCurrentRequest();
        $cartRepository = $this->doctrine->getRepository(Cart::class);
        $cartId = $request->getSession()->get('cartId');

        if(!is_null($cartId)) {
            return $cartRepository->find($cartId);
        }

    }

}