<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskPackageInput
{
    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public string $type;

    #[Groups(['pricing_deliveries', 'delivery_create'])]
    public int $quantity;
}
