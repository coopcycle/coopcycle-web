<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\DTO;

use AppBundle\Entity\Address;

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

    /**
     * Creates an Address entity from this DTO.
     */
    public function toAddress(): Address
    {
        $streetAddress = trim(
            $this->streetAddress . ' ' .
            $this->addressLine1 . ' ' .
            $this->addressLine2
        );

        $entityAddress = new Address();
        $entityAddress->setStreetAddress(trim($streetAddress));
        $entityAddress->setAddressLocality($this->city);
        $entityAddress->setPostalCode($this->postalCode);
        $entityAddress->setAddressCountry($this->country);

        return $entityAddress;
    }
}