<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskPackageDto
{
    #[Groups(["task"])]
    public readonly string $shortCode;

    #[Groups(["task"])]
    public readonly string $name;

    #[Groups(["task"])]
    public readonly int $averageVolumeUnits;

    #[Groups(["task"])]
    public readonly int $quantity;

    public function __construct(
        string $shortCode,
        string $name,
        int $averageVolumeUnits,
        int $quantity)
    {
        $this->shortCode = $shortCode;
        $this->name = $name;
        $this->averageVolumeUnits = $averageVolumeUnits;
        $this->quantity = $quantity;
    }
}
