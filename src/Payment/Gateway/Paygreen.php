<?php

namespace AppBundle\Payment\Gateway;

use AppBundle\Entity\Refund;
use AppBundle\Payment\GatewayInterface;
use AppBundle\Service\PaygreenManager;
use Sylius\Component\Payment\Model\PaymentInterface;

class Paygreen implements GatewayInterface
{
    public function __construct(private PaygreenManager $paygreenManager)
    {}

    public function authorize(PaymentInterface $payment, array $context = [])
    {
        // With Paygreen, the payment has already been authorized
        // We double-check the status of the payment
        if (!$this->paygreenManager->isPaymentOrderAuthorized($context['token'])) {
            throw new \Exception('Invalid Payment Order');
        }

        // TODO Retrieve PaymentOrder, and update payments accordingly
        // We need to check inside transactions.operations.instrument
        // to find the amount & platform of each operation
    }

    public function capture(PaymentInterface $payment)
    {
        $this->paygreenManager->capture($payment);
    }

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        $refund = $payment->addRefund($amount);

        // TODO Implement refunds

        return $refund;
    }
}
