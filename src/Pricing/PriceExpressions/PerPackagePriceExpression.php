<?php

namespace AppBundle\Pricing\PriceExpressions;

class PerPackagePriceExpression extends PriceExpression
{
    public function __construct(
        public readonly string $packageName,
        public readonly int $unitPrice,
        public readonly int $offset,
        public readonly int $discountPrice
    ) {
    }

    public function hasDiscount(): bool
    {
        return $this->offset !== 0;
    }
}
