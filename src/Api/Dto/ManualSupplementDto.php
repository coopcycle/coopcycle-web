<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Delivery\PricingRule;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

class ManualSupplementDto
{
    #[Groups(['delivery', 'delivery_create', 'pricing_deliveries'])]
    #[SerializedName('@id')]
    #[Assert\NotBlank]
    public PricingRule|null $pricingRule = null;

    #[Groups(['delivery', 'delivery_create', 'pricing_deliveries'])]
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\GreaterThan(0)]
    public int|null $quantity = null;
}
