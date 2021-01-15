<?php

namespace AppBundle\Domain\Order\Command;

use AppBundle\Entity\Refund as RefundEntity;
use Sylius\Component\Payment\Model\PaymentInterface;

class Refund
{
    private $payment;
    private $amount;
    private $liableParty;
    private $comments;

    public function __construct(PaymentInterface $payment, $amount = null, $liableParty = RefundEntity::LIABLE_PARTY_PLATFORM, $comments = '')
    {
        $this->payment = $payment;
        $this->amount = $amount;
        $this->liableParty = $liableParty;
        $this->comments = $comments;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getLiableParty()
    {
        return $this->liableParty;
    }

    public function getComments()
    {
        return $this->comments;
    }
}
