<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;

final class CartSessionInput
{
    /**
     * @var LocalBusiness
     */
    public $restaurant;

    /**
     * @var Address|null
     */
    public $shippingAddress;
}
