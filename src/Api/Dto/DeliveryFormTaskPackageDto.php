<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class DeliveryFormTaskPackageDto
{
    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public string $type;

    #[Groups(['delivery', 'pricing_deliveries', 'delivery_create'])]
    public int|string $quantity;
}
