<?php

namespace AppBundle\Payment\Gateway;

use AppBundle\Entity\Refund;
use AppBundle\Payment\GatewayInterface;
use AppBundle\Service\StripeManager;
use Sylius\Component\Payment\Model\PaymentInterface;

class Stripe implements GatewayInterface
{
    public function __construct(private StripeManager $stripeManager)
    {}

    public function authorize(PaymentInterface $payment, array $context = [])
    {
        $paymentIntent = $payment->getPaymentIntent();

        if ($paymentIntent !== $context['token']) {
            throw new \Exception('Payment Intent mismatch');
        }

        if ($payment->requiresUseStripeSDK()) {
            $this->stripeManager->confirmIntent($payment);
        }

        if ($payment->hasToSavePaymentMethod()) {
            $this->stripeManager->attachPaymentMethodToCustomer($payment);
        }
    }

    public function capture(PaymentInterface $payment)
    {
        $this->stripeManager->capture($payment);
    }

    public function refund(PaymentInterface $payment, $amount = null): Refund
    {
        $refund = $payment->addRefund($amount);

        $stripeRefund = $this->stripeManager->refund($payment, $amount);
        $payment->addStripeRefund($stripeRefund);
        $refund->setData(['stripe_refund_id' => $stripeRefund->id]);

        return $refund;
    }
}
