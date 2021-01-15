<?php

namespace AppBundle\Entity;

use AppBundle\Enum\FoodEstablishment;

/**
 *
 */
class Restaurant extends LocalBusiness
{
    public function __construct()
    {
        $this->type = FoodEstablishment::RESTAURANT;

        parent::__construct();
    }
}
