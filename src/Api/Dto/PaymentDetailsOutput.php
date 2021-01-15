<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class PaymentDetailsOutput
{
    /**
     * @var string|null
     * @Groups({"order"})
     */
    public $stripeAccount;
}
