<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyPrice
{
    public function __construct(
        public readonly int $price,
        public readonly bool $isFixed = true,
        public readonly bool $preventDiscounts = false,
    ) {}
}
