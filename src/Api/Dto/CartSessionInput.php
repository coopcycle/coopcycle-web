<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\LocalBusiness;

final class CartSessionInput
{
    /**
     * @var LocalBusiness
     */
    public $restaurant;
}
