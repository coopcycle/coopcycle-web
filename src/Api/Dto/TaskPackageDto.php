<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class TaskPackageDto
{
    #[Groups(["task"])]
    public readonly string $name;

    #[Groups(["task"])]
    public readonly int $quantity;

    //todo; add more fields

    public function __construct(string $name, int $quantity)
    {
        $this->name = $name;
        $this->quantity = $quantity;
    }
}
