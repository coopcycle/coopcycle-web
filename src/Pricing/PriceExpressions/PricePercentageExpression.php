<?php

namespace AppBundle\Pricing\PriceExpressions;

class PricePercentageExpression extends PriceExpression
{
    // 10000 = 100.00% - no change
    public const PERCENTAGE_NEUTRAL = 10000;

    public function __construct(
        public readonly int $percentage
    ) {
    }
}
