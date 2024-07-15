<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

final class PaymentDetailsOutput
{
    /**
     * @var string|null
     * @Groups({"order"})
     */
    public $stripeAccount;

    /**
     * @var array|null
     * @Groups({"order"})
     */
    public $breakdown;
}
