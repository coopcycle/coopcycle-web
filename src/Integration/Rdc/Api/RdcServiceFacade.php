<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Api;

use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use AppBundle\Integration\Rdc\Enum\ActionState;
use AppBundle\Integration\Rdc\Enum\ActivitySubtype;
use AppBundle\Integration\Rdc\Enum\ActivityType;
use AppBundle\Integration\Rdc\Enum\ExecutionStatus;
use AppBundle\Integration\Rdc\Enum\InvoiceStatus;
use AppBundle\Integration\Rdc\Enum\ResponseStatus;
use AppBundle\Integration\Rdc\Enum\ServiceNature;
use AppBundle\Integration\Rdc\Enum\ServiceStatus;
use AppBundle\Integration\Rdc\Enum\ServiceSubtype;
use AppBundle\Integration\Rdc\Enum\ServiceType;
use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use Psr\Log\LoggerInterface;

class RdcServiceFacade
{
    private ?RdcClientInterface $client = null;
    private ?string $connectedConnectionId = null;

    public function __construct(
        private readonly RdcClientFactory $rdcClientFactory,
        private readonly LoggerInterface $logger,
        private readonly string $connectionId = '', //FIXME: Should use sort of ctx resolver instead.
    ) {}

    public function getConnectionId(): string
    {
        return $this->connectedConnectionId ?? $this->connectionId ?: '';
    }

    public function getRdcClient(): RdcClientInterface
    {
        return $this->getClient();
    }

    public function createService(
        string $serviceId,
        ?RdcApiServiceRequest $apiRequest = null,
        ?string $barcode = null,
        ?string $contractRef = null,
        ?string $serviceRequestUri = null,
        array $pickupAddress = [],
        array $dropoffAddress = [],
        array $pickupTimeSlot = [],
        array $dropoffTimeSlot = [],
    ): string
    {
        $this->logger->info('Creating RDC service', [
            'service_id' => $serviceId,
            'barcode' => $barcode,
            'contract_ref' => $contractRef,
        ]);

        $serviceData = $this->buildServiceData(
            $serviceId,
            $apiRequest,
            $barcode,
            $contractRef,
            $serviceRequestUri,
            $pickupAddress,
            $dropoffAddress,
            $pickupTimeSlot,
            $dropoffTimeSlot
        );

        $response = $this->getClient()->post(
            '/services',
            $serviceData,
            ['source' => 'true', 'id' => $serviceId]
        );

        $statusCode = $response->getStatusCode();
        $returnedServiceId = $serviceId;

        try {
            $responseData = $response->toArray();
            $returnedServiceId = $responseData['id'] ?? $serviceId;
            $this->logger->info('Service response data', [
                'response_data' => $responseData,
                'parsed_service_id' => $returnedServiceId,
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Could not parse service response as JSON', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->logger->info('RDC service created', [
            'service_id' => $serviceId,
            'returned_service_id' => $returnedServiceId,
            'response_status' => $statusCode,
        ]);

        return $returnedServiceId;
    }

    public function buildServiceData(
        string $serviceId,
        ?RdcApiServiceRequest $apiRequest = null,
        ?string $barcode = null,
        ?string $contractRef = null,
        ?string $serviceRequestUri = null,
        array $pickupAddress = [],
        array $dropoffAddress = [],
        array $pickupTimeSlot = [],
        array $dropoffTimeSlot = [],
    ): array
    {
        $serviceData = [
            'id' => $serviceId,
            'serviceType' => ServiceType::TRANSPORT->value,
            'serviceStatus' => ServiceStatus::PROPOSED->value,
            'serviceSubtype' => ServiceSubtype::CARGOBIKE_TRANSPORT->value,
            'serviceName' => sprintf('livraison APPLICOLIS n° %s', $contractRef ?? $serviceId),
            'executionStatus' => ExecutionStatus::SCHEDULED->value,
            'serviceNature' => ServiceNature::LOGISTICS->value,
            'isDangerous' => $apiRequest?->isDangerous ?? false,
            'invoiceStatus' => InvoiceStatus::NOT_INVOICED->value,
            'serviceAgreementReference' => $contractRef,
            'externalReferences' => $this->mapExternalReferences($barcode, $contractRef),
        ];

        //FIXME: Add legal requestor here.
        $serviceData['requestor'] = ['legalEntityName' => 'APPLICOLIS'];

        if ($serviceRequestUri !== null) {
            $serviceData['serviceRequests'] = [['uri' => $serviceRequestUri]];
        }

        if ($pickupAddress !== []) {
            $serviceData['startLocation'] = $this->buildServiceLocation($pickupAddress, $pickupTimeSlot, 'LOADING');
        }

        if ($dropoffAddress !== []) {
            $serviceData['endLocation'] = $this->buildServiceLocation($dropoffAddress, $dropoffTimeSlot, 'UNLOADING');
        }

        return $serviceData;
    }

    public function createActivity(
        string $serviceId,
        string $serviceUri,
        array $pickupAddress,
        array $dropoffAddress,
        array $pickupTimeSlot,
        array $dropoffTimeSlot,
    ): string
    {
        $activityId = sprintf('%s.transport', $serviceId);

        $this->logger->info('Creating RDC activity', [
            'service_id' => $serviceId,
            'activity_id' => $activityId,
        ]);

        $activityData = $this->buildActivityData($activityId, $serviceUri, $pickupAddress, $dropoffAddress, $pickupTimeSlot, $dropoffTimeSlot);

        $response = $this->getClient()->post(
            '/activities',
            $activityData,
            ['source' => 'true', 'id' => $activityId]
        );

        $statusCode = $response->getStatusCode();
        $returnedActivityId = $activityId;

        if ($statusCode >= 200 && $statusCode < 300) {
            try {
                $responseData = $response->toArray();
                $returnedActivityId = $responseData['id'] ?? $activityId;
            } catch (\Exception $e) {
                $this->logger->debug('Empty response body, using activityId');
            }
        }

        $this->logger->info('RDC activity created successfully', [
            'service_id' => $serviceId,
            'activity_id' => $returnedActivityId,
            'response_status' => $statusCode,
        ]);

        return $returnedActivityId;
    }

    public function buildActivityData(
        string $activityId,
        string $serviceUri,
        array $pickupAddress,
        array $dropoffAddress,
        array $pickupTimeSlot,
        array $dropoffTimeSlot,
    ): array
    {
        return [
            'id' => $activityId,
            'activityType' => ActivityType::TRANSPORT->value,
            'executionStatus' => ExecutionStatus::SCHEDULED->value,
            'activityName' => 'Transport par velo',
            'activitySubtype' => ActivitySubtype::CARGOBIKE_TRANSPORT->value,
            'sequenceNumber' => 1,
            'relatedObject' => ['uri' => $serviceUri],
            'startLocation' => $this->buildActivityLocation($pickupAddress, $pickupTimeSlot, 'LOADING'),
            'endLocation' => $this->buildActivityLocation($dropoffAddress, $dropoffTimeSlot, 'UNLOADING'),
        ];
    }

    public function linkActivityToService(string $serviceId, string $activityId): void
    {
        $activityUri = sprintf('%s/activities/%s', $this->getClient()->getBaseUrl(), $activityId);

        $this->getClient()->patch(sprintf('/services/%s', $serviceId), [
            'scheduledActivities' => ['uri' => $activityUri],
        ]);

        $this->logger->info('Linked activity to service', [
            'service_id' => $serviceId,
            'activity_id' => $activityId,
        ]);
    }

    public function buildActivityLocation(array $address, array $timeSlot, string $actionType): array
    {
        return $this->buildLocation($address, $timeSlot, $actionType);
    }

    public function buildServiceLocation(array $address, array $timeSlot, string $actionType): array
    {
        return $this->buildLocation($address, $timeSlot, $actionType);
    }

    private function buildLocation(array $address, array $timeSlot, string $actionType): array
    {
        $location = [
            'location' => [
                'address' => [
                    'addressCountry' => [
                        'countryCode' => $address['country'] ?? 'FR',
                        'countryName' => $this->getCountryName($address['country'] ?? 'FR'),
                    ],
                    'addressLocality' => $address['city'] ?? '',
                    'postalCode' => $address['postalCode'] ?? '',
                    'addressLines' => $address['addressLines'] ?? [$address['streetAddress'] ?? ''],
                ],
                'locationName' => '',
            ],
        ];

        if ($timeSlot !== []) {
            $location['plannedStartTimeRange'] = ['earliestDateTime' => $timeSlot['start']->format('Y-m-d\TH:i:s\Z')];
            $location['plannedEndTimeRange'] = ['latestDateTime' => $timeSlot['end']->format('Y-m-d\TH:i:s\Z')];
        }

        $actionName = $actionType === 'LOADING' ? 'Chargement' : 'Déchargement';
        $action = [
            'actionName' => $actionName,
            'actionState' => ActionState::SCHEDULED->value,
            'actionType' => 'HANDLING',
            'actionSubtype' => $actionType,
            'sequenceNumber' => 1,
        ];

        $location['actions'] = [$action];

        return $location;
    }

    public function mapExternalReferences(?string $barcode, ?string $contractRef): array
    {
        $refs = [];

        if ($contractRef !== null) {
            $refs[] = [
                'externalReferenceType' => 'PROVIDER_ID',
                'reference' => $contractRef,
                'description' => 'Contract reference',
            ];
        }

        if ($barcode !== null) {
            $refs[] = [
                'externalReferenceType' => 'REQUESTOR_LABEL_ID',
                'reference' => $barcode,
                'description' => 'Barcode label',
            ];
        }

        return $refs;
    }

    public function notifyOriginNode(string $loUri, string $serviceId, ?RdcApiServiceRequest $apiRequest = null, ?int $loRevision = null): void
    {
        $this->logger->info('Notifying origin node by patching service-request', [
            'lo_uri' => $loUri,
            'service_id' => $serviceId,
        ]);

        $localServiceUrl = sprintf('%s/services/%s', $this->getClient()->getBaseUrl(), $serviceId);

        $operations = [
            [
                'op' => 'add',
                'path' => '/responseStatus',
                'value' => ResponseStatus::APPROVED->value,
            ],
            [
                'op' => 'add',
                'path' => '/service',
                'value' => [
                    'uri' => $localServiceUrl,
                ],
            ],
            [
                'op' => 'add',
                'path' => '/isDangerous',
                'value' => $apiRequest?->isDangerous ?? false,
            ],
            [
                'op' => 'add',
                'path' => '/serviceAgreementReference',
                'value' => $apiRequest?->serviceAgreementReference ?? '',
            ],
            [
                'op' => 'add',
                'path' => '/serviceNature',
                'value' => $apiRequest?->serviceNature?->value ?? '',
            ],
            [
                'op' => 'add',
                'path' => '/serviceType',
                'value' => $apiRequest?->serviceType?->value ?? '',
            ],
            [
                'op' => 'add',
                'path' => '/serviceSubtype',
                'value' => $apiRequest?->serviceSubtype?->value ?? '',
            ],
            [
                'op' => 'add',
                'path' => '/serviceName',
                'value' => $apiRequest?->serviceName ?? '',
            ],
        ];

        $changeRequest = [
            'logisticsObjectRevision' => $loRevision,
            'operations' => $operations,
        ];

        $remoteChangeRequestUrl = sprintf('%s/change-requests', $loUri);

        $response = $this->getClient()->postRemote($remoteChangeRequestUrl, $changeRequest);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            try {
                $body = $response->getContent();
            } catch (\Exception $e) {
                $body = null;
            }
            $this->logger->error('Notify origin node failed', [
                'lo_uri' => $loUri,
                'service_id' => $serviceId,
                'status' => $statusCode,
                'response_body' => $body ?: 'empty',
            ]);
            throw new \RuntimeException(sprintf(
                'Failed to notify origin node: %s',
                $body ?: sprintf('HTTP %d', $statusCode)
            ));
        }

        $this->logger->info('Origin node notified successfully', [
            'lo_uri' => $loUri,
            'service_id' => $serviceId,
            'linked_service_url' => $localServiceUrl,
            'response_status' => $statusCode,
        ]);
    }

    private function getClient(): RdcClientInterface
    {
        //FIXME: This must use context
        if (!is_null($this->client) && $this->connectedConnectionId === $this->connectionId) {
            return $this->client;
        }

        $connectionId = $this->connectionId;

        if (empty($connectionId)) {
            $ids = $this->rdcClientFactory->getConnectionIds();
            if ($ids !== []) {
                $connectionId = $ids[0];
            } else {
                $connectionId = 'default';
            }
        }

        $client = $this->rdcClientFactory->create($connectionId);
        if ($client === null) {
            throw new \RuntimeException(sprintf('RDC connection "%s" not found or disabled', $connectionId));
        }

        $this->client = $client;
        $this->connectedConnectionId = $connectionId;

        return $this->client;
    }

    private function getCountryName(string $countryCode): string
    {
        return match ($countryCode) {
            'FR' => 'France',
            default => $countryCode,
        };
    }
}
