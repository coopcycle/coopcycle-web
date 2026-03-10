<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;

class AddressResolver
{
    public static function resolveAddress(LocalBusiness $shop): ?Address
    {
        return $shop->getAddress();
    }
}
