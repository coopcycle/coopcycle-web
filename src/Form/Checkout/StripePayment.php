<?php

namespace AppBundle\Form\Checkout;

class StripePayment
{
    private ?string $stripeToken = null;
    private ?string $savedPaymentMethodId = null;

    public function getStripeToken(): ?string
    {
        return $this->stripeToken;
    }

    public function setStripeToken(string $stripeToken): void
    {
        $this->stripeToken = $stripeToken;
    }

    public function getSavedPaymentMethodId(): ?string
    {
        return $this->savedPaymentMethodId;
    }

    public function setSavedPaymentMethodId(string $savedPaymentMethodId): void
    {
        $this->savedPaymentMethodId = $savedPaymentMethodId;
    }
}
