<?php

namespace AppBundle\Domain\Order\Command;

use Sylius\Component\Payment\Model\PaymentInterface;

class Refund
{
    private $payment;
    private $amount;
    private $refundApplicationFee;

    public function __construct(PaymentInterface $payment, $amount = null, $refundApplicationFee = false)
    {
        $this->payment = $payment;
        $this->amount = $amount;
        $this->refundApplicationFee = $refundApplicationFee;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getRefundApplicationFee()
    {
        return $this->refundApplicationFee;
    }
}
