<?php

namespace AppBundle\Api\Dto;

use Doctrine\Common\Collections\ArrayCollection;

final class StripePaymentMethodsOutput
{
    public $methods;

    public function __construct()
    {
        $this->methods = new ArrayCollection();
    }

    public function addMethod($paymentMethod)
    {
        $this->methods->add([
            "id" => $paymentMethod->id,
            "expMonth" => $paymentMethod->card->exp_month,
            "expYear" => $paymentMethod->card->exp_year,
            "last4" => $paymentMethod->card->last4,
            "brand" => $paymentMethod->card->brand
        ]);
    }

    public function getMethods()
    {
        return $this->methods;
    }
}
