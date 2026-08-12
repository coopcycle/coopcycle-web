<?php

namespace AppBundle\Message\Zelty;

class ProcessZeltyCatalog
{
    public function __construct(
        public readonly int $restaurantId,
        public readonly string $s3Key,
    ) {}
}
