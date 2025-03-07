<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class ConfigurePaymentInput
{
    /**
     * @var string
     * @Groups({"order_configure_payment"})
     */
    public string $paymentMethod;
}

