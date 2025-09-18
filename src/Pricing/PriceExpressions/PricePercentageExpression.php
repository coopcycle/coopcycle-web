<?php

namespace AppBundle\Pricing\PriceExpressions;

class PricePercentageExpression extends PriceExpression
{
    public function __construct(
        public readonly int $percentage
    ) {
    }
}
