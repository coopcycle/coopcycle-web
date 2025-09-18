<?php

namespace AppBundle\ExpressionLanguage;

readonly class PriceEvaluation
{
    public function __construct(
        public int $unitPrice,
        public int $quantity
    )
    {
    }
}
