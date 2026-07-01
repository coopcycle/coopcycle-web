<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyAvailability
{
    public function __construct(
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
    ) {}
}
