<?php

namespace AppBundle\Payment;

use AppBundle\Entity\Refund;
use Sylius\Component\Payment\Model\PaymentInterface;

interface GatewayInterface
{
    public function authorize(PaymentInterface $payment, array $context = []);
    public function capture(PaymentInterface $payment);
    public function refund(PaymentInterface $payment, $amount = null): Refund;
}
