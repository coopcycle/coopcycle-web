<?php

namespace AppBundle\Sylius\Payment;

use AppBundle\Entity\Refund;
use Stripe\Refund as StripeRefund;

trait RefundTrait
{
    public function addRefund(int $amount, string $liableParty, string $comments = '')
    {
        $refund = new Refund();
        $refund->setPayment($this);
        $refund->setLiableParty($liableParty);
        $refund->setAmount($amount);
        $refund->setComments($comments);

        $this->refunds->add($refund);

        return $refund;
    }

    public function addStripeRefund(StripeRefund $refund)
    {
        $refunds = [];
        if (isset($this->details['refunds'])) {
            $refunds = $this->details['refunds'];
        }

        $refunds[] = [
            'id' => $refund->id,
            'amount' => $refund->amount,
        ];

        $this->details = array_merge($this->details, ['refunds' => $refunds]);
    }

    public function hasRefunds()
    {
        return count($this->refunds) > 0;
    }

    public function getRefunds()
    {
        return $this->refunds;
    }

    public function getRefundTotal()
    {
        $total = 0;
        foreach ($this->getRefunds() as $refund) {
            $total += $refund->getAmount();
        }

        return $total;
    }

    public function getRefundAmount()
    {
        return $this->getAmount() - $this->getRefundTotal();
    }
}
