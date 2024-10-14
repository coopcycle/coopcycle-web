<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

final class PaymentDetailsOutput
{
    /**
     * @var string|null
     * @Groups({"payment_details"})
     */
    public $stripeAccount;

    /**
     * @var Collection
     * @Groups({"payment_details"})
     */
    public $payments;
}
