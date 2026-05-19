<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\DTO;

use AppBundle\Integration\Rdc\Enum\ContactRole;
use AppBundle\Integration\Rdc\Enum\ExecutionStatus;
use AppBundle\Integration\Rdc\Enum\ExternalReferenceType;
use AppBundle\Integration\Rdc\Enum\Incoterm;
use AppBundle\Integration\Rdc\Enum\InvoiceStatus;
use AppBundle\Integration\Rdc\Enum\ServiceNature;
use AppBundle\Integration\Rdc\Enum\ServiceStatus;
use AppBundle\Integration\Rdc\Enum\ServiceSubtype;
use AppBundle\Integration\Rdc\Enum\ServiceType;
use DateTimeImmutable;

final class RdcApiService
{
    /**
     * @param array<int, array> $contacts
     * @param array<int, array> $externalReferences
     * @param array<int, array> $saleOperations
     * @param array<int, array> $specialCharacteristics
     * @param array<int, array> $specialInstructions
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $uri = null,
        public readonly ?string $memberIdentifier = null,
        public readonly ?int $revision = null,
        public readonly ?ExecutionStatus $executionStatus = null,
        public readonly ?InvoiceStatus $invoiceStatus = null,
        public readonly bool $isDangerous = false,
        public readonly ?string $productDescription = null,
        public readonly ?string $serviceAgreementReference = null,
        public readonly ?string $serviceDescription = null,
        public readonly ?string $serviceName = null,
        public readonly ?ServiceNature $serviceNature = null,
        public readonly ?ServiceStatus $serviceStatus = null,
        public readonly ?ServiceSubtype $serviceSubtype = null,
        public readonly ?ServiceType $serviceType = null,
        public readonly ?Incoterm $incoterm = null,
        public readonly array $contacts = [],
        public readonly array $externalReferences = [],
        public readonly ?array $startLocation = null,
        public readonly ?array $endLocation = null,
        public readonly ?array $provider = null,
        public readonly ?array $requestor = null,
        public readonly ?array $scheduledActivities = null,
        public readonly ?array $serviceRequests = null,
        public readonly ?array $saleOperations = null,
        public readonly ?array $sla = null,
        public readonly ?array $specialCharacteristics = null,
        public readonly ?array $specialInstructions = null,
        public readonly ?array $totalOperationAmount = null,
        public readonly ?array $declaredValue = null,
        public readonly ?array $invoicedParty = null,
        public readonly ?array $finalLocation = null,
        public readonly ?array $originLocation = null,
        public readonly ?string $aclId = null,
        public readonly ?string $createdBy = null,
        public readonly ?string $updatedBy = null,
        public readonly ?string $updatedByMember = null,
        public readonly ?string $creationTraceparent = null,
        public readonly ?string $updateTraceparent = null,
    ) {}

    public static function parse(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            uri: $data['uri'] ?? null,
            memberIdentifier: $data['memberIdentifier'] ?? null,
            revision: isset($data['revision']) ? (int) $data['revision'] : null,
            executionStatus: isset($data['executionStatus'])
                ? ExecutionStatus::tryFrom($data['executionStatus'])
                : null,
            invoiceStatus: isset($data['invoiceStatus'])
                ? InvoiceStatus::tryFrom($data['invoiceStatus'])
                : null,
            isDangerous: $data['isDangerous'] ?? false,
            productDescription: $data['productDescription'] ?? null,
            serviceAgreementReference: $data['serviceAgreementReference'] ?? null,
            serviceDescription: $data['serviceDescription'] ?? null,
            serviceName: $data['serviceName'] ?? null,
            serviceNature: isset($data['serviceNature'])
                ? ServiceNature::tryFrom($data['serviceNature'])
                : null,
            serviceStatus: isset($data['serviceStatus'])
                ? ServiceStatus::tryFrom($data['serviceStatus'])
                : null,
            serviceSubtype: isset($data['serviceSubtype'])
                ? ServiceSubtype::tryFrom($data['serviceSubtype'])
                : null,
            serviceType: isset($data['serviceType'])
                ? ServiceType::tryFrom($data['serviceType'])
                : null,
            incoterm: isset($data['incoterm'])
                ? Incoterm::tryFrom($data['incoterm'])
                : null,
            contacts: $data['contacts'] ?? [],
            externalReferences: $data['externalReferences'] ?? [],
            startLocation: $data['startLocation'] ?? null,
            endLocation: $data['endLocation'] ?? null,
            provider: $data['provider'] ?? null,
            requestor: $data['requestor'] ?? null,
            scheduledActivities: $data['scheduledActivities'] ?? null,
            serviceRequests: $data['serviceRequests'] ?? null,
            saleOperations: $data['saleOperations'] ?? null,
            sla: $data['sla'] ?? null,
            specialCharacteristics: $data['specialCharacteristics'] ?? null,
            specialInstructions: $data['specialInstructions'] ?? null,
            totalOperationAmount: $data['totalOperationAmount'] ?? null,
            declaredValue: $data['declaredValue'] ?? null,
            invoicedParty: $data['invoicedParty'] ?? null,
            finalLocation: $data['finalLocation'] ?? null,
            originLocation: $data['originLocation'] ?? null,
            aclId: $data['aclId'] ?? null,
            createdBy: $data['createdBy'] ?? null,
            updatedBy: $data['updatedBy'] ?? null,
            updatedByMember: $data['updatedByMember'] ?? null,
            creationTraceparent: $data['creationTraceparent'] ?? null,
            updateTraceparent: $data['updateTraceparent'] ?? null,
        );
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function getRevision(): ?int
    {
        return $this->revision;
    }

    public function getScheduledActivityUri(): ?string
    {
        if (isset($this->scheduledActivities['uri'])) {
            return $this->scheduledActivities['uri'];
        }
        return null;
    }

    public function getServiceRequestUri(): ?string
    {
        if (is_array($this->serviceRequests)) {
            foreach ($this->serviceRequests as $sr) {
                if (isset($sr['uri'])) {
                    return $sr['uri'];
                }
            }
        }
        return null;
    }

    public function getExternalReferenceByType(string $type): ?array
    {
        foreach ($this->externalReferences as $ref) {
            if (($ref['externalReferenceType'] ?? '') === $type) {
                return $ref;
            }
        }
        return null;
    }

    public function getStartTimeRange(): ?array
    {
        return $this->startLocation['requestedStartTimeRange'] ?? null;
    }

    public function getEndTimeRange(): ?array
    {
        return $this->endLocation['requestedEndTimeRange'] ?? null;
    }

    public function getPlannedStartTimeRange(): ?array
    {
        return $this->startLocation['plannedStartTimeRange']
            ?? $this->startLocation['requestedStartTimeRange']
            ?? null;
    }

    public function getPlannedEndTimeRange(): ?array
    {
        return $this->endLocation['plannedEndTimeRange']
            ?? $this->endLocation['requestedEndTimeRange']
            ?? null;
    }

    public function getPickupAddress(): ServiceRequestAddress
    {
        $address = $this->startLocation['location']['address'] ?? [];
        return new ServiceRequestAddress(
            streetAddress: $address['addressLines'][0] ?? '',
            addressLine1: $address['addressLines'][1] ?? '',
            addressLine2: $address['addressLines'][2] ?? '',
            postalCode: $address['postalCode'] ?? '',
            city: $address['addressLocality'] ?? '',
            country: $address['addressCountry']['countryCode'] ?? '',
        );
    }

    public function getDropoffAddress(): ServiceRequestAddress
    {
        $address = $this->endLocation['location']['address'] ?? [];
        return new ServiceRequestAddress(
            streetAddress: $address['addressLines'][0] ?? '',
            addressLine1: $address['addressLines'][1] ?? '',
            addressLine2: $address['addressLines'][2] ?? '',
            postalCode: $address['postalCode'] ?? '',
            city: $address['addressLocality'] ?? '',
            country: $address['addressCountry']['countryCode'] ?? '',
        );
    }

    public function getPickupTimeSlot(): TimeSlot
    {
        $range = $this->startLocation['requestedStartTimeRange'] ?? [];
        return new TimeSlot(
            start: isset($range['earliestDateTime']) ? new DateTimeImmutable($range['earliestDateTime']) : null,
            end: isset($range['latestDateTime']) ? new DateTimeImmutable($range['latestDateTime']) : null,
        );
    }

    public function getDropoffTimeSlot(): TimeSlot
    {
        $range = $this->endLocation['requestedEndTimeRange'] ?? [];
        return new TimeSlot(
            start: isset($range['earliestDateTime']) ? new DateTimeImmutable($range['earliestDateTime']) : null,
            end: isset($range['latestDateTime']) ? new DateTimeImmutable($range['latestDateTime']) : null,
        );
    }

    public function getRecipientContact(): ServiceRequestContact
    {
        $contact = $this->getContactByRole(ContactRole::RECIPIENT);
        if ($contact === null) {
            return new ServiceRequestContact();
        }
        return new ServiceRequestContact(
            name: $contact['familyName'] ?? '',
            phone: $contact['telephone'] ?? '',
            email: $contact['email'] ?? '',
        );
    }

    public function getBarcode(): ?string
    {
        $ref = $this->getExternalReferenceByType(ExternalReferenceType::REQUESTOR_LABEL_ID->value);
        return $ref['reference'] ?? null;
    }

    public function getExternalRef(): ?string
    {
        $ref = $this->getExternalReferenceByType(ExternalReferenceType::REQUESTOR_ID->value);
        return $ref['reference'] ?? null;
    }

    public function getContractRef(): ?string
    {
        return $this->serviceAgreementReference;
    }

    private function getContactByRole(ContactRole $role): ?array
    {
        foreach ($this->contacts as $contact) {
            if (($contact['role'] ?? '') === $role->value) {
                return $contact;
            }
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uri' => $this->uri,
            'memberIdentifier' => $this->memberIdentifier,
            'revision' => $this->revision,
            'executionStatus' => $this->executionStatus?->value,
            'invoiceStatus' => $this->invoiceStatus?->value,
            'isDangerous' => $this->isDangerous,
            'productDescription' => $this->productDescription,
            'serviceAgreementReference' => $this->serviceAgreementReference,
            'serviceDescription' => $this->serviceDescription,
            'serviceName' => $this->serviceName,
            'serviceNature' => $this->serviceNature?->value,
            'serviceStatus' => $this->serviceStatus?->value,
            'serviceSubtype' => $this->serviceSubtype?->value,
            'serviceType' => $this->serviceType?->value,
            'incoterm' => $this->incoterm?->value,
            'contacts' => $this->contacts,
            'externalReferences' => $this->externalReferences,
            'startLocation' => $this->startLocation,
            'endLocation' => $this->endLocation,
            'provider' => $this->provider,
            'requestor' => $this->requestor,
            'scheduledActivities' => $this->scheduledActivities,
            'serviceRequests' => $this->serviceRequests,
            'saleOperations' => $this->saleOperations,
            'sla' => $this->sla,
            'specialCharacteristics' => $this->specialCharacteristics,
            'specialInstructions' => $this->specialInstructions,
            'totalOperationAmount' => $this->totalOperationAmount,
            'declaredValue' => $this->declaredValue,
            'invoicedParty' => $this->invoicedParty,
            'finalLocation' => $this->finalLocation,
            'originLocation' => $this->originLocation,
            'aclId' => $this->aclId,
            'createdBy' => $this->createdBy,
            'updatedBy' => $this->updatedBy,
            'updatedByMember' => $this->updatedByMember,
            'creationTraceparent' => $this->creationTraceparent,
            'updateTraceparent' => $this->updateTraceparent,
        ];
    }
}