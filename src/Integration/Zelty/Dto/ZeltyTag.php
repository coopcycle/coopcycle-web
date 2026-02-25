<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyTag
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $img = null,
        public readonly ?string $color = null,
        public readonly bool $disabled = false,
        public readonly array $itemIds = [],
    ) {}
}
