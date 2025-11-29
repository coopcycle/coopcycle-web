<?php

namespace AppBundle\Pricing\PriceExpressions;

/**
 * Represents a price expression that couldn't be parsed into a specific type
 */
class UnparsablePriceExpression extends PriceExpression
{
    public function __construct(
        public readonly string $expression
    ) {
    }
}
