<?php

namespace AppBundle\Pricing\PriceExpressions;

class PercentagePriceExpression extends PriceExpression
{
    public function __construct(
        public readonly int $percentage
    ) {
    }
}
