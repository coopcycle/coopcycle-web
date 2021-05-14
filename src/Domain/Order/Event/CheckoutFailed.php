<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class CheckoutFailed extends Event implements DomainEvent
{
    private $payment;
    private $reason;

    public static function messageName(): string
    {
        return 'order:checkout_failed';
    }

    public function __construct(OrderInterface $order, PaymentInterface $payment, $reason = null)
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
