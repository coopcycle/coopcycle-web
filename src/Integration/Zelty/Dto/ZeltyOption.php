<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyOption
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly array $valueIds = [],
    ) {}
}
