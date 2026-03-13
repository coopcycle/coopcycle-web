<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyPrice
{
    public function __construct(
        public readonly int $price,
        //NOTE: Might remove isFixed & preventDiscounts
        public readonly bool $isFixed = true,
        public readonly bool $preventDiscounts = false,
        // NOTE: Might be worth to make its own class.
        public readonly array $overrides = [],
    ) {}
}
