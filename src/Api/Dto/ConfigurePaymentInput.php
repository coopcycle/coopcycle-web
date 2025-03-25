<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class ConfigurePaymentInput
{
    #[Groups(['order_configure_payment'])]
    public string $paymentMethod;
}

