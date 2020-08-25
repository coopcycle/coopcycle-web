<?php

namespace AppBundle\Domain\Order\Command;

use Sylius\Component\Payment\Model\PaymentInterface;

class Refund
{
    private $payment;
    private $amount;

    public function __construct(PaymentInterface $payment, $amount = null)
    {
        $this->payment = $payment;
        $this->amount = $amount;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function getAmount()
    {
        return $this->amount;
    }
}
