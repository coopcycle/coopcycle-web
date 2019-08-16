<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

final class RestaurantInput
{
    /**
     * @Groups({"restaurant_update"})
     */
    public $hasMenu;

    /**
     * @Groups({"restaurant_update"})
     */
    public $state;
}
