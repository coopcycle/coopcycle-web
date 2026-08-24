<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\DTO;

use AppBundle\Integration\Rdc\Enum\ActivitySubtype;
use AppBundle\Integration\Rdc\Enum\ActivityType;
use AppBundle\Integration\Rdc\Enum\ExecutionStatus;
use DateTimeImmutable;

final class RdcApiActivity
{
    /**
     * @param array<int, array> $contacts
     * @param array<int, array> $externalReferences
     * @param array<int, array> $specialCharacteristics
     * @param array<int, array> $specialInstructions
     * @param array<int, array> $documents
     * @param array<int, array> $inTransitActions
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $uri = null,
        public readonly ?string $memberIdentifier = null,
        public readonly ?int $revision = null,
        public readonly ?string $activityName = null,
        public readonly ?string $processReference = null,
        public readonly ?int $sequenceNumber = null,
        public readonly bool $isStartingService = false,
        public readonly bool $isEndingService = false,
        public readonly ?ExecutionStatus $executionStatus = null,
        public readonly ?ActivityType $activityType = null,
        public readonly ?ActivitySubtype $activitySubtype = null,
        public readonly ?string $relatedObject = null,
        public readonly array $contacts = [],
        public readonly array $externalReferences = [],
        public readonly ?array $startLocation = null,
        public readonly ?array $endLocation = null,
        public readonly ?array $delegatedService = null,
        public readonly array $documents = [],
        public readonly ?array $estimatedCostOperations = null,
        public readonly array $inTransitActions = [],
        public readonly ?array $executionLocation = null,
        public readonly ?array $specialCharacteristics = null,
        public readonly ?array $specialInstructions = null,
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
            activityName: $data['activityName'] ?? null,
            processReference: $data['processReference'] ?? null,
            sequenceNumber: isset($data['sequenceNumber']) ? (int) $data['sequenceNumber'] : null,
            isStartingService: $data['isStartingService'] ?? false,
            isEndingService: $data['isEndingService'] ?? false,
            executionStatus: isset($data['executionStatus'])
                ? ExecutionStatus::tryFrom($data['executionStatus'])
                : null,
            activityType: isset($data['activityType'])
                ? ActivityType::tryFrom($data['activityType'])
                : null,
            activitySubtype: isset($data['activitySubtype'])
                ? ActivitySubtype::tryFrom($data['activitySubtype'])
                : null,
            relatedObject: is_array($data['relatedObject'] ?? null)
                ? ($data['relatedObject']['uri'] ?? null)
                : ($data['relatedObject'] ?? null),
            contacts: $data['contacts'] ?? [],
            externalReferences: $data['externalReferences'] ?? [],
            startLocation: $data['startLocation'] ?? null,
            endLocation: $data['endLocation'] ?? null,
            delegatedService: $data['delegatedService'] ?? null,
            documents: $data['documents'] ?? [],
            estimatedCostOperations: $data['estimatedCostOperations'] ?? null,
            inTransitActions: $data['inTransitActions'] ?? [],
            executionLocation: $data['executionLocation'] ?? null,
            specialCharacteristics: $data['specialCharacteristics'] ?? null,
            specialInstructions: $data['specialInstructions'] ?? null,
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

    public function getRelatedObjectUri(): ?string
    {
        return $this->relatedObject;
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

    public function getStartTimeSlot(): TimeSlot
    {
        $range = $this->startLocation['requestedStartTimeRange']
            ?? $this->startLocation['plannedStartTimeRange']
            ?? [];
        return new TimeSlot(
            start: isset($range['earliestDateTime']) ? new DateTimeImmutable($range['earliestDateTime']) : null,
            end: isset($range['latestDateTime']) ? new DateTimeImmutable($range['latestDateTime']) : null,
        );
    }

    public function getEndTimeSlot(): TimeSlot
    {
        $range = $this->endLocation['requestedEndTimeRange']
            ?? $this->endLocation['plannedEndTimeRange']
            ?? [];
        return new TimeSlot(
            start: isset($range['earliestDateTime']) ? new DateTimeImmutable($range['earliestDateTime']) : null,
            end: isset($range['latestDateTime']) ? new DateTimeImmutable($range['latestDateTime']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uri' => $this->uri,
            'memberIdentifier' => $this->memberIdentifier,
            'revision' => $this->revision,
            'activityName' => $this->activityName,
            'processReference' => $this->processReference,
            'sequenceNumber' => $this->sequenceNumber,
            'isStartingService' => $this->isStartingService,
            'isEndingService' => $this->isEndingService,
            'executionStatus' => $this->executionStatus?->value,
            'activityType' => $this->activityType?->value,
            'activitySubtype' => $this->activitySubtype?->value,
            'relatedObject' => $this->relatedObject,
            'contacts' => $this->contacts,
            'externalReferences' => $this->externalReferences,
            'startLocation' => $this->startLocation,
            'endLocation' => $this->endLocation,
            'delegatedService' => $this->delegatedService,
            'documents' => $this->documents,
            'estimatedCostOperations' => $this->estimatedCostOperations,
            'inTransitActions' => $this->inTransitActions,
            'executionLocation' => $this->executionLocation,
            'specialCharacteristics' => $this->specialCharacteristics,
            'specialInstructions' => $this->specialInstructions,
            'aclId' => $this->aclId,
            'createdBy' => $this->createdBy,
            'updatedBy' => $this->updatedBy,
            'updatedByMember' => $this->updatedByMember,
            'creationTraceparent' => $this->creationTraceparent,
            'updateTraceparent' => $this->updateTraceparent,
        ];
    }
}