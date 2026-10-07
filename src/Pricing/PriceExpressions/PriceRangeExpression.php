<?php

namespace AppBundle\Pricing\PriceExpressions;

class PriceRangeExpression extends PriceExpression
{
    public function __construct(
        public readonly string $attribute,
        public readonly int $price,
        public readonly int $step,
        public readonly int $threshold,
        /**
         * The variable the range is charged per unit of, null when it is charged once.
         */
        public readonly ?string $multiplier = null
    ) {
    }
}
