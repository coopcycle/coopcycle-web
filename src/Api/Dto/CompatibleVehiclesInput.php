<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Vehicle;
use Symfony\Component\Serializer\Annotation\Groups;

final class CompatibleVehiclesInput
{
    /**
     * @var Vehicle[]
     */
    #[Groups(['trailer_update'])]
    public $compatibleVehicles = [];
}
