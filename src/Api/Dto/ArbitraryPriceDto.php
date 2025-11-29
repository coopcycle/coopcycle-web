<?php

namespace AppBundle\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class ArbitraryPriceDto
{
    public function __construct(
        #[Groups(['delivery', 'delivery_create'])]
        public readonly int $variantPrice,
        #[Groups(['delivery', 'delivery_create'])]
        public readonly ?string $variantName,
    ) {
    }
}
