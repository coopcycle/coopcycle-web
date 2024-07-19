<?php

namespace AppBundle\Payment;

use AppBundle\Sylius\Order\OrderInterface;

class GatewayResolver
{
    private $country;
    private $mercadopagoCountries;
    private $forceStripe;

    public function __construct(string $country,
        $mercadopagoCountries = [],
        $forceStripe = false,
        private bool $paygreenEnabled = false)
    {
        $this->country = $country;
        $this->mercadopagoCountries = $mercadopagoCountries;
        $this->forceStripe = $forceStripe;
    }

    public function resolveForCountry($country)
    {
        if ($this->forceStripe) {
            return 'stripe';
        }

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
        if ($gateway === 'paygreen') {
            return $this->paygreenEnabled;
        }

        return $gateway === $this->resolveForCountry($this->country);
    }
}
