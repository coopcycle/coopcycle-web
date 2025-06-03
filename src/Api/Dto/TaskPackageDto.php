<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskPackageDto
{
    #[Groups(['task', 'delivery'])]
    public string $short_code;

    #[Groups(['task', 'delivery'])]
    public string $name;

    #[Groups(['task', 'delivery', 'pricing_deliveries', 'delivery_create'])]
    public string $type;

    #[Groups(['task', 'delivery'])]
    public int $volume_per_package;

    #[Groups(['task', 'delivery', 'pricing_deliveries', 'delivery_create'])]
    public int $quantity;

}
