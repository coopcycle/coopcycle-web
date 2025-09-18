<?php

namespace AppBundle\Pricing\PriceExpressions;

class PerPackagePriceExpression extends PriceExpression
{
    public function __construct(
        public readonly string $packageName,
        public readonly int $unitPrice,
        public readonly ?int $offset = null,
        public readonly ?int $discountPrice = null
    ) {
    }
}
