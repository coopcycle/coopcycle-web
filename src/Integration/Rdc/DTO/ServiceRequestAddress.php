<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\DTO;

class ServiceRequestAddress
{
    public function __construct(
        public readonly string $streetAddress = '',
        public readonly string $addressLine1 = '',
        public readonly string $addressLine2 = '',
        public readonly string $postalCode = '',
        public readonly string $city = '',
        public readonly string $country = '',
    ) {}

    public function isEmpty(): bool
    {
        return $this->streetAddress === ''
            && $this->postalCode === ''
            && $this->city === '';
    }
}