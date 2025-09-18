<?php

namespace AppBundle\Pricing\PriceExpressions;

class FixedPriceExpression extends PriceExpression
{
    public function __construct(
        public readonly int $value
    ) {
    }
}
