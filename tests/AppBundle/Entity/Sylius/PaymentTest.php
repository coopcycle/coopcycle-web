<?php

namespace Tests\AppBundle\Entity\Sylius;

use AppBundle\Entity\Refund;
use AppBundle\Entity\Sylius\Payment;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    public function testRefunds()
    {
        $payment = new Payment();
        $payment->setAmount(2000);

        $payment->addRefund(500, Refund::LIABLE_PARTY_PLATFORM);

        $this->assertTrue($payment->hasRefunds());
        $this->assertEquals(500, $payment->getRefundTotal());
        $this->assertEquals(1500, $payment->getRefundAmount());
    }
}
