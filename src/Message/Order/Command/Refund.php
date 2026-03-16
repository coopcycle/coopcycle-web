<?php

namespace AppBundle\Message\Order\Command;

use AppBundle\Entity\Refund as RefundEntity;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class Refund
{
    private $subject;
    private $amount;
    private $liableParty;
    private $comments;

    public function __construct(OrderInterface|PaymentInterface $subject, $amount = null, $liableParty = RefundEntity::LIABLE_PARTY_PLATFORM, $comments = '')
    {
        $this->subject = $subject;
        $this->amount = $amount;
        $this->liableParty = $liableParty;
        $this->comments = $comments;
    }

    public function getSubject(): OrderInterface|PaymentInterface
    {
        return $this->subject;
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
