<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Mapper;

use AppBundle\Integration\Rdc\DTO\ServiceRequest;
use AppBundle\Integration\Rdc\DTO\ServiceRequestAddress;
use AppBundle\Integration\Rdc\DTO\ServiceRequestContact;
use AppBundle\Integration\Rdc\DTO\TimeSlot;
use AppBundle\Integration\Rdc\Enum\ExternalReferenceType;
use DateTimeImmutable;

class ServiceRequestMapper
{
    /**
     * Maps RDC service-request array to a ServiceRequest DTO.
     */
    public function map(array $serviceRequest): ServiceRequest
    {
        $addresses = [
            'pickup' => $this->getAddress($serviceRequest['startLocation']['location']['address'] ?? []),
            'dropoff' => $this->getAddress($serviceRequest['endLocation']['location']['address'] ?? []),
        ];

        $timeSlots = [
            'pickup' => $this->getTimeSlot($serviceRequest['startLocation'] ?? []),
            'dropoff' => $this->getTimeSlot($serviceRequest['endLocation'] ?? []),
        ];

        $contacts = $this->getContacts($serviceRequest['contacts'] ?? []);
        $refs = $this->getExternalReferences($serviceRequest['externalReferences'] ?? []);

        return new ServiceRequest(
            addresses: $addresses,
            timeSlots: $timeSlots,
            contacts: $contacts,
            externalRef: $refs['externalRef'],
            barcode: $refs['barcode'],
            contractRef: $serviceRequest['serviceAgreementReference'] ?? null,
        );
    }

    /**
     * @param array $location
     */
    public function getTimeSlot(array $location): TimeSlot
    {
        $start = null;
        $end = null;

        if (isset($location['requestedStartTimeRange']['earliestDateTime'])) {
            $start = new DateTimeImmutable($location['requestedStartTimeRange']['earliestDateTime']);
        }

        if (isset($location['requestedEndTimeRange']['latestDateTime'])) {
            $end = new DateTimeImmutable($location['requestedEndTimeRange']['latestDateTime']);
        }

        return new TimeSlot(start: $start, end: $end);
    }

    /**
     * @param array<string, mixed> $contacts
     * @return array<string, ServiceRequestContact>
     */
    public function getContacts(array $contacts): array
    {
        $result = [];

        foreach ($contacts as $contact) {
            $role = $contact['role'] ?? '';

            $contactData = new ServiceRequestContact(
                name: $contact['name'] ?? '',
                phone: $contact['phone'] ?? $contact['telephone'] ?? '',  // Handle both 'phone' and 'telephone' from spec
                email: $contact['email'] ?? '',
            );

            switch ($role) {
                case 'RECIPIENT':
                case 'CUSTOMER_DISPATCH':
                    $result[$role] = $contactData;
                    break;
                default:
                    break;
            }
        }

        return $result;
    }

    /**
     * @param array<array{type?: string, reference?: string, externalReferenceType?: string}> $refs
     * @return array{externalRef: string|null, barcode: string|null}
     */
    public function getExternalReferences(array $refs): array
    {
        $externalRef = null;
        $barcode = null;

        foreach ($refs as $ref) {
            $type = $ref['type'] ?? $ref['externalReferenceType'] ?? null;
            $reference = $ref['reference'] ?? null;

            if ($type === null || $reference === null) {
                continue;
            }

            $enumType = ExternalReferenceType::tryFrom($type);

            switch ($enumType) {
                case ExternalReferenceType::REQUESTOR_ID:
                case ExternalReferenceType::CUSTOMER_ID:
                    $externalRef = $reference;
                    break;
                case ExternalReferenceType::REQUESTOR_LABEL_ID:
                    $barcode = $reference;
                    break;
                default:
                    break;
            }
        }

        return ['externalRef' => $externalRef, 'barcode' => $barcode];
    }

    /**
     * @param array $location
     */
    public static function getAddress(array $location): ServiceRequestAddress
    {
        // Handle addressLines format from RDC spec
        $addressLines = $location['addressLines'] ?? [];

        // Build streetAddress from addressLines if available
        $streetAddress = '';
        if (!empty($addressLines)) {
            $streetAddress = implode(' ', $addressLines);
        } elseif (isset($location['streetAddress'])) {
            $streetAddress = $location['streetAddress'];
        }

        // Extract postalCode and addressLocality from nested addressCountry if present
        $postalCode = $location['postalCode'] ?? '';
        $city = $location['addressLocality'] ?? $location['city'] ?? '';
        $country = '';

        if (isset($location['addressCountry']['countryCode'])) {
            $country = $location['addressCountry']['countryCode'];
        } elseif (isset($location['country'])) {
            $country = $location['country'];
        }

        return new ServiceRequestAddress(
            streetAddress: $streetAddress,
            addressLine1: $location['addressLine1'] ?? '',
            addressLine2: $location['addressLine2'] ?? '',
            postalCode: $postalCode,
            city: $city,
            country: $country,
        );
    }
}