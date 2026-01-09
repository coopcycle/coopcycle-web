<?php

namespace AppBundle\Message;

use AppBundle\Entity\LocalBusiness;

class ResetRestaurantState
{
    private int $id;

    public function __construct(LocalBusiness $restaurant)
    {
        $this->id = $restaurant->getId();
    }

    public function getId(): int
    {
        return $this->id;
    }
}

