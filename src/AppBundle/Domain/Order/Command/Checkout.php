<?php

namespace AppBundle\Domain\Order\Command;

use AppBundle\Sylius\Order\OrderInterface;

class Checkout
{
    private $order;
    private $stripeToken;

    public function __construct(OrderInterface $order, $stripeToken = null)
    {
        $this->order = $order;
        $this->stripeToken = $stripeToken;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getStripeToken()
    {
        return $this->stripeToken;
    }
}

