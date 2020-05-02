<?php

namespace AppBundle\Service;

use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CartProviderService
{
    private $cartContext;
    private $serializer;
    private $country;

    public function __construct(CartContextInterface $cartContext, SerializerInterface $serializer, string $country)
    {
        $this->cartContext = $cartContext;
        $this->serializer = $serializer;
        $this->country = $country;
    }

    public function getCart()
    {
        return $this->cartContext->getCart();
    }

    public function normalize(OrderInterface $cart)
    {
        return $this->serializer->normalize($cart, 'jsonld', [
            'is_web' => true,
            'groups' => ['order', 'address', sprintf('address_%s', $this->country)]
        ]);
    }
}
