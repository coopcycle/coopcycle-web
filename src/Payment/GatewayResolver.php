<?php

namespace AppBundle\Payment;

use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

class GatewayResolver
{
    public function __construct(
        private string $country,
        private array $mercadopagoCountries = [],
        private bool $paygreenEnabled = false)
    {}

    public function resolveForCountry($country)
    {
        if (in_array($country, $this->mercadopagoCountries)) {
            return 'mercadopago';
        }

        return 'stripe';
    }

    public function resolveForOrder(OrderInterface $order)
    {
        if ($order->supportsPaygreen()) {
            return 'paygreen';
        }

        return $this->resolveForCountry($this->country);
    }

    public function resolve()
    {
        return $this->resolveForCountry($this->country);
    }

    public function supports($gateway): bool
    {
        switch ($gateway) {
            case 'paygreen':
                return $this->paygreenEnabled;
            case 'mercadopago':
                return in_array($this->country, $this->mercadopagoCountries);
            case 'stripe':
                // FIXME Stripe is not supported everywhere
                return true;
        }

        return false;
    }

    public function resolveForPayment(PaymentInterface $payment)
    {
        $details = $payment->getDetails();

        if ($payment->hasPaygreenPaymentOrderId()) {
            return 'paygreen';
        }

        return $this->resolveForOrder($payment->getOrder());
    }
}
