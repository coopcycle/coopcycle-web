<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\DTO;

class ServiceRequest
{
    /**
     * @param array<string, ServiceRequestContact> $contacts
     * @param array<string, ServiceRequestAddress> $addresses
     * @param array<string, TimeSlot> $timeSlots
     */
    public function __construct(
        public readonly array $addresses = [],
        public readonly array $timeSlots = [],
        public readonly array $contacts = [],
        public readonly ?string $externalRef = null,
        public readonly ?string $barcode = null,
        public readonly ?string $contractRef = null,
        public readonly array $packages = [],
    ) {}
}