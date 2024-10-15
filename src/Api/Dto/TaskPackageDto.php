<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskPackageDto
{
    #[Groups(["task"])]
    public readonly string $short_code;

    #[Groups(["task"])]
    public readonly string $name;

    #[Groups(["task"])]
    public readonly string $type;

    #[Groups(["task"])]
    public readonly int $volume_per_package;

    #[Groups(["task"])]
    public readonly int $quantity;

    public function __construct(
        string $shortCode,
        string $name,
        int $averageVolumeUnits,
        int $quantity)
    {
        $this->short_code = $shortCode;
        $this->name = $name;
        //FIXME; why do we have name and type with the same value?
        $this->type = $name;
        $this->volume_per_package = $averageVolumeUnits;
        $this->quantity = $quantity;
    }
}
