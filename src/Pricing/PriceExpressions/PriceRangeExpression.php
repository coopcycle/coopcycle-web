<?php

namespace AppBundle\Pricing\PriceExpressions;

class PriceRangeExpression extends PriceExpression
{
    public function __construct(
        public readonly string $attribute,
        public readonly int $price,
        public readonly int $step,
        public readonly int $threshold
    ) {
    }
}
