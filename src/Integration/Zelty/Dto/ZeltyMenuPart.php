<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyMenuPart
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $internalId = null,
        public readonly ?string $name = null,
        public readonly array $dishIds = [],
    ) {}
}
