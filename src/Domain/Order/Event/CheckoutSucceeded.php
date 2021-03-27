<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class CheckoutSucceeded extends Event implements DomainEvent
{
    private $payment;

    public static function messageName(): string
    {
        return 'order:checkout_succeeded';
    }

    public function __construct(OrderInterface $order, ?PaymentInterface $payment = null)
    {
        parent::__construct($order);

        $this->payment = $payment;
    }

    public function getPayment()
    {
        return $this->payment;
    }
}
