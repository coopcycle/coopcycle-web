<?php

namespace AppBundle\Pricing;

class ManualSupplements
{
    /**
     * @param ManualSupplement[] $orderSupplements
     */
    public function __construct(
        public readonly array $orderSupplements,
    ) {
    }
}
