<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\DTO;

use DateTimeImmutable;

class TimeSlot
{
    public function __construct(
        public readonly ?DateTimeImmutable $start = null,
        public readonly ?DateTimeImmutable $end = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->start === null && $this->end === null;
    }
}