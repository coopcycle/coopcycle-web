<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyTaxRule
{
    public function __construct(
        public readonly string $taxId,
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?int $rate = null,
        public readonly ?string $idRestaurant = null,
        public readonly ?string $taxAccountingCode = null,
        public readonly ?string $taxAccountingLabel = null,
    ) {}
}
