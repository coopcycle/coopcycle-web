<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Sylius\ProductOptionValue;

class ProductOptionValueWithQuantity
{
    public function __construct(
        public readonly ProductOptionValue $productOptionValue,
        public readonly int $quantity
    ) {
    }
}
