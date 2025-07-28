<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ManualSupplementDto
{
    #[Groups(['delivery', 'delivery_create', 'pricing_deliveries'])]
    #[Assert\NotBlank]
    public string|null $uri = null;

    #[Groups(['delivery', 'delivery_create', 'pricing_deliveries'])]
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\GreaterThan(0)]
    public int|null $quantity = null;
}
