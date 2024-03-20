<?php

namespace AppBundle\Form\Checkout;

use AppBundle\Sylius\Order\OrderInterface;

class CheckoutPayment
{
    private ?StripePayment $stripePayment = null;

    public function __construct(private OrderInterface $order)
    {
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function getStripePayment(): ?StripePayment
    {
        return $this->stripePayment;
    }

    public function setStripePayment(StripePayment $stripePayment): void
    {
        $this->stripePayment = $stripePayment;
    }
}
