<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;

class OrderDuplicate
{

    public function __construct(
        public Delivery $delivery,
        public ArbitraryPrice|null $previousArbitraryPrice = null,
    ) {
    }
}
