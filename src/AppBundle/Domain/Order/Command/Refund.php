<?php

namespace AppBundle\Domain\Order\Command;

use Sylius\Component\Payment\Model\PaymentInterface;

class Refund
{
    private $payment;

    public function __construct(PaymentInterface $payment)
    {
        $this->payment = $payment;
    }

    public function getPayment()
    {
        return $this->payment;
    }
}
