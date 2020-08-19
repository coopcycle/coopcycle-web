<?php

namespace AppBundle\Payment;

class GatewayResolver
{
    private $country;
    private $mercadopagoCountries;

    public function __construct(string $country, $mercadopagoCountries = [])
    {
        $this->country = $country;
        $this->mercadopagoCountries = $mercadopagoCountries;
    }

    public function resolveForCountry($country)
    {
        if (in_array($country, $this->mercadopagoCountries)) {
            return 'mercadopago';
        }

        return 'stripe';
    }

    public function resolve()
    {
        return $this->resolveForCountry($this->country);
    }
}
