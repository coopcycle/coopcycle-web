<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class StripeOutput
{
    /**
     * @var boolean
     * @Groups({"order"})
     */
    public $requiresAction;

    /**
     * @var string
     * @Groups({"order"})
     */
    public $paymentIntentClientSecret;

    /**
     * @var string
     * @Groups({"order"})
     */
    public $paymentIntentId;
}
