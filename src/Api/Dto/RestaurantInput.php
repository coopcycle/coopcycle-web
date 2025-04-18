<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Sylius\Taxon;
use Symfony\Component\Serializer\Annotation\Groups;

final class RestaurantInput
{
    /**
     * @var Taxon
     */
    #[Groups(['restaurant_update'])]
    public $hasMenu;

    #[Groups(['restaurant_update'])]
    public $state;
}
