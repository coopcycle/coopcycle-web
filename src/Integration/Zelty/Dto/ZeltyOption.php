<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyOption
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly array $valueIds = [],
        public readonly int $min_choices = 0,
        public readonly int $max_choices = 1,
    ) {}
}
