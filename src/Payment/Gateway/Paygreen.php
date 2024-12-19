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
        // With Paygreen, the payment has already been authorized client-side
        // We double-check the status of the payment
        if (!$po = $this->paygreenManager->getPaymentOrder($context['token'])) {
            throw new \Exception(sprintf('Payment Order "%s" not found', $context['token']));
        }

        if ($po['status'] !== 'payment_order.authorized') {
            throw new \Exception(sprintf('Payment Order "%s" is not authorized', $context['token']));
        }

        // There may have been multiple Paygreen operations
        // We convert them to Payment objects
        $payments = $this->paygreenManager->getPaymentsFromPaymentOrder($context['token']);

        $order = $payment->getOrder();
        foreach ($order->getPayments() as $p) {
            $order->removePayment($p);
        }

        foreach ($payments as $p) {
            $order->addPayment($p);
        }
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
