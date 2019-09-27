<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class OrderFulfilled extends Event implements DomainEvent, HasIconInterface
{
    private $payment;

    public static function messageName()
    {
        return 'order:fulfilled';
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

    public static function iconName()
    {
        return 'check';
    }
}

