<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Order\Event;
use AppBundle\Entity\StripePayment;
use AppBundle\Sylius\Order\OrderInterface;

class CheckoutSucceeded extends Event implements DomainEvent
{
    private $payment;

    public static function messageName()
    {
        return 'order:checkout_succeeded';
    }

    public function __construct(OrderInterface $order, StripePayment $payment)
    {
        parent::__construct($order);

        $this->payment = $payment;
    }

    public function getPayment()
    {
        return $this->payment;
    }
}
