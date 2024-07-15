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
        $forceStripe = false)
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
        return $this->resolve();
    }

    public function resolve()
    {
        return $this->resolveForCountry($this->country);
    }
}
