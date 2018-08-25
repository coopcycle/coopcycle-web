<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\Order\DomainEvent;
use AppBundle\Domain\Order\Event;
use AppBundle\Entity\StripePayment;
use AppBundle\Sylius\Order\OrderInterface;

class CheckoutFailed extends Event implements DomainEvent
{
    private $payment;
    private $reason;

    public static function messageName()
    {
        return 'order:checkout_failed';
    }

    public function __construct(OrderInterface $order, StripePayment $payment, $reason = null)
    {
        parent::__construct($order);

        $this->payment = $payment;
        $this->reason = $reason;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function getReason()
    {
        return $this->reason;
    }
}
