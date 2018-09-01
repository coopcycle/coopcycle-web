<?php

namespace AppBundle\Domain\Restaurant\Event;

// use AppBundle\Domain\DomainEvent;
// use AppBundle\Domain\Order\Event;
// use AppBundle\Entity\StripePayment;
// use AppBundle\Sylius\Order\OrderInterface;
use SimpleBus\Message\Name\NamedMessage;

class StateChanged implements NamedMessage
{
    // private $payment;
    // private $reason;

    public static function messageName()
    {
        return 'restaurant:state_changed';
    }

    // public function __construct(OrderInterface $order, StripePayment $payment, $reason = null)
    // {
    //     parent::__construct($order);

    //     $this->payment = $payment;
    //     $this->reason = $reason;
    // }

    // public function getPayment()
    // {
    //     return $this->payment;
    // }

    // public function getReason()
    // {
    //     return $this->reason;
    // }
}
