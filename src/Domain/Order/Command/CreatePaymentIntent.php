<?php

namespace AppBundle\Domain\Order\Command;

use AppBundle\Sylius\Order\OrderInterface;

class CreatePaymentIntent
{
    private $order;

    public function __construct(OrderInterface $order, $paymentMethodId)
    {
        $this->order = $order;
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getPaymentMethodId()
    {
        return $this->paymentMethodId;
    }
}

