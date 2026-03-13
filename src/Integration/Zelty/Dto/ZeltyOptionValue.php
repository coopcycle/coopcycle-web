<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyOptionValue
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $img = null,
        public readonly bool $disabled = false,
        public readonly bool $isSoldOut = false,
        public readonly ?ZeltyPrice $price = null,
        public readonly ?ZeltyAvailability $availability = null,
    ) {}
}
