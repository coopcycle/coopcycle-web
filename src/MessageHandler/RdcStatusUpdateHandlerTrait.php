<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Integration\Rdc\Api\RdcClientFactory;
use AppBundle\Integration\Rdc\Enum\ResponseStatus;
use AppBundle\Message\RdcDropoffStatusUpdateMessage;
use AppBundle\Message\RdcPickupStatusUpdateMessage;
use AppBundle\Integration\Rdc\Api\RdcClientInterface;
use AppBundle\Integration\Rdc\Enum\ActionState;
use AppBundle\Integration\Rdc\Enum\EventCode;
use AppBundle\Integration\Rdc\Enum\EventDomain;
use AppBundle\Integration\Rdc\Enum\EventType;
use AppBundle\Integration\Rdc\Enum\ExecutionStatus;
use AppBundle\Integration\Rdc\Enum\ServiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

trait RdcStatusUpdateHandlerTrait
{
    public function getRdcClient(Task $task): ?RdcClientInterface
    {
        $delivery = $task->getDelivery();
        if (is_null($delivery)) {
            return null;
        }

        $store = $delivery->getStore();
        if (is_null($store)) {
            return null;
        }

        $rdcConnectionId = $store->getRdcConnectionId();
        if (is_null($rdcConnectionId)) {
            return null;
        }

        return $this->rdcClientFactory->create($rdcConnectionId);
    }

    public function processStatusUpdate(RdcPickupStatusUpdateMessage|RdcDropoffStatusUpdateMessage $message, array $config): void
    {
        $task = $this->entityManager->find(Task::class, $message->taskId);
        if (is_null($task)) {
            return;
        }

        $rdcClient = $this->getRdcClient($task);
        if (is_null($rdcClient)) {
            $this->logger->warning('No RDC client available', [
                'task_id' => $message->taskId,
            ]);
            return;
        }

        $delivery = $task->getDelivery();
        $serviceId = sprintf('%s', $delivery->getId());
        $actionTime = $message->actionTime ?? new \DateTimeImmutable();

        $activityId = $task->getType() === Task::TYPE_DROPOFF
            ? $this->getActivityId(sprintf('%s', $delivery->getPickup()->getId()))
            : $this->getActivityId(sprintf('%s', $message->taskId));

        $address = $task->getAddress();
        $location = $this->buildLocationUpdate($address, $config['actionType'], $actionTime, $task);

        match ([$message->coopcycleStatus, $task->getType()]) {
            [Task::STATUS_DONE, Task::TYPE_PICKUP] => $this->handleStatusDoing($rdcClient, $serviceId, $activityId, $location, $actionTime, $config, $task),
            [Task::STATUS_DONE, Task::TYPE_DROPOFF] => $this->handleStatusDone($rdcClient, $serviceId, $activityId, $location, $actionTime, $config, $task),
            [Task::STATUS_CANCELLED, Task::TYPE_PICKUP] => $this->handleStatusCancelled($rdcClient, $serviceId, $activityId, $location, $actionTime, $config, $task),
            [Task::STATUS_CANCELLED, Task::TYPE_DROPOFF] => $this->handleStatusCancelled($rdcClient, $serviceId, $activityId, $location, $actionTime, $config, $task),
            default => null
        };

        $this->logger->info('Processed RDC status update', [
            'task_id' => $message->taskId,
            'service_id' => $serviceId,
            'status' => $message->coopcycleStatus,
        ]);
    }

    private function handleStatusDoing(
        RdcClientInterface $rdcClient,
        string $serviceId,
        string $activityId,
        array $location,
        \DateTimeImmutable $actionTime,
        array $config,
        Task $task
    ): void {
        $this->patchExecution($rdcClient, 'services', $serviceId, ExecutionStatus::STARTED->value, $location, false);
        $this->patchExecution($rdcClient, 'activities', $activityId, ExecutionStatus::STARTED->value, $location, false);

        $this->postEvents($rdcClient, 'services', $serviceId, $config['startServiceEvents'] ?? [], $location, $actionTime, $task);
        $this->postEvents($rdcClient, 'activities', $activityId, $config['startActivityEvents'] ?? [], $location, $actionTime, $task);
    }

    private function handleStatusDone(
        RdcClientInterface $rdcClient,
        string $serviceId,
        string $activityId,
        array $location,
        \DateTimeImmutable $actionTime,
        array $config,
        Task $task
    ): void {
        $this->patchExecution($rdcClient, 'services', $serviceId, ExecutionStatus::FINISHED->value, $location, true);
        $this->patchExecution($rdcClient, 'activities', $activityId, ExecutionStatus::FINISHED->value, $location, true);

        $this->postEvents($rdcClient, 'services', $serviceId, $config['serviceEvents'] ?? [], $location, $actionTime, $task);
        $this->postEvents($rdcClient, 'activities', $activityId, $config['activityEvents'] ?? [], $location, $actionTime, $task);
    }

    private function handleStatusCancelled(
        RdcClientInterface $rdcClient,
        string $serviceId,
        string $activityId,
        array $location,
        \DateTimeImmutable $actionTime,
        array $config,
        Task $task
    ): void {
        $this->patchExecution($rdcClient, 'services', $serviceId, ExecutionStatus::CANCELLED->value, $location, true);
        $this->patchExecution($rdcClient, 'activities', $activityId, ExecutionStatus::CANCELLED->value, $location, true);
        $this->notifyRemoteCancellation($rdcClient, $task);
    }

    private function notifyRemoteCancellation(RdcClientInterface $rdcClient, Task $task): void
    {
        $pickup = $this->extractPickup($task);
        if (is_null($pickup)) {
            return;
        }

        $loUri = $this->extractLoUri($pickup);
        if (is_null($loUri)) {
            $this->logger->warning('No rdc_lo_uri found in pickup metadata', [
                'delivery_id' => $task->getDelivery()?->getId(),
            ]);
            return;
        }

        $loRevision = $this->fetchLoRevision($rdcClient, $loUri);
        if (is_null($loRevision)) {
            return;
        }

        $changeRequest = $this->buildCancellationChangeRequest($loRevision, $rdcClient->getMemberIdentifier());
        $this->postCancellationChangeRequest($rdcClient, $loUri, $changeRequest);
    }

    private function extractPickup(Task $task): ?Task
    {
        $delivery = $task->getDelivery();
        if (is_null($delivery)) {
            return null;
        }

        return $delivery->getPickup();
    }

    private function extractLoUri(Task $pickup): ?string
    {
        $metadata = $pickup->getMetadata();

        return $metadata['rdc_lo_uri'] ?? null;
    }

    private function fetchLoRevision(RdcClientInterface $rdcClient, string $loUri): ?string
    {
        try {
            $response = $rdcClient->getRemote($loUri);
            $loRevision = $response->getHeaders(false)['x-revision'][0] ?? null;

            if (is_null($loRevision)) {
                $this->logger->warning('No revision found in remote response', [
                    'lo_uri' => $loUri,
                ]);
            }

            return $loRevision;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch remote revision', [
                'lo_uri' => $loUri,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildCancellationChangeRequest(string $loRevision, string $memberIdentifier): array
    {
        return [
            'logisticsObjectRevision' => $loRevision,
            'operations' => [
    [
                'op' => 'add',
                'path' => '/responseStatus',
                'value' => ResponseStatus::CANCELLED->value,
    ]
            ],
            'requestorMemberIdentifier' => $memberIdentifier,
        ];
    }

    private function postCancellationChangeRequest(
        RdcClientInterface $rdcClient,
        string $loUri,
        array $changeRequest
    ): void {
        $remoteChangeRequestUrl = sprintf('%s/change-requests?source=true', $loUri);

        try {
            $response = $rdcClient->postRemote($remoteChangeRequestUrl, $changeRequest);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->error('Remote cancellation failed', [
                    'lo_uri' => $loUri,
                    'status' => $statusCode,
                ]);
                throw new \RuntimeException(sprintf('Remote cancellation failed: HTTP %d', $statusCode));
            }

            $this->logger->info('Remote cancellation notified successfully', [
                'lo_uri' => $loUri,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to post cancellation change request', [
                'lo_uri' => $loUri,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function patchExecution(
        RdcClientInterface $rdcClient,
        string $resourceType,
        string $resourceId,
        string $executionStatus,
        array $location,
        bool $isEndLocation = false
    ): void {
        $payload = $isEndLocation
            ? ['executionStatus' => $executionStatus, 'endLocation' => $location]
            : ['executionStatus' => $executionStatus, 'startLocation' => $location];

        $rdcClient->patch(sprintf('/%s/%s', $resourceType, $resourceId), $payload);
    }

    private function postEvents(
        RdcClientInterface $rdcClient,
        string $resourceType,
        string $resourceId,
        array $events,
        array $location,
        \DateTimeImmutable $actionTime,
        Task $task
    ): void {
        $documents = $this->buildDocumentsFromTask($task);
        foreach ($events as $event) {
            $this->postExecutionEvent(
                $rdcClient,
                $resourceType,
                $resourceId,
                $event['code'],
                $event['type'],
                $location,
                sprintf($event['description'], $resourceId),
                $actionTime,
                $documents
            );
        }
    }

    private function postExecutionEvent(
        RdcClientInterface $rdcClient,
        string $resourceType,
        string $resourceId,
        EventCode $code,
        EventType $eventType,
        array $location,
        string $description,
        \DateTimeImmutable $actionTime,
        array $documents = []
    ): void {
        $payload = [
            'eventType' => $eventType->value,
            'code' => $code->value,
            'domain' => EventDomain::BUSINESS->value,
            'description' => $description,
            'location' => $location,
            'actualDateTime' => $actionTime->format(\DateTimeInterface::ATOM),
            'creationDateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        if (!empty($documents)) {
            $payload['documents'] = $documents;
        }

        $rdcClient->post(sprintf('/%s/%s/events', $resourceType, $resourceId), $payload);
    }

    private function buildDocumentsFromTask(Task $task): array
    {
        $documents = [];
        $subtype = $task->isPickup() ? 'DELIVERY_ADDITIONAL_EVIDENCE' : 'PROOF_OF_DELIVERY';
        foreach ($task->getImages() as $image) {
            $documents[] = $this->buildDocumentFromImage($image, $subtype);
        }
        return $documents;
    }

    private function buildDocumentFromImage(TaskImage $image, string $subtype): array
    {
        return [
            'cmsUri' => $this->urlGenerator->generate(
                'task_image_public',
                ['path' => $image->getImageName()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'documentName' => 'Photo de preuve de livraison',
            'documentType' => 'TRANSPORT',
            'documentSubtype' => $subtype,
        ];
    }

    private function getActivityId(string $taskId): string
    {
        return sprintf('%s.transport', $taskId);
    }

    private function buildLocationUpdate(Address $address, string $actionType, \DateTimeImmutable $actionTime, ?Task $task = null): array
    {
        $isDropoff = !is_null($task) && $task->getType() === Task::TYPE_DROPOFF;

        return [
            'address' => [
                'addressCountry' => ['countryCode' => 'FR', 'countryName' => 'France'],
                'addressLocality' => $address->getAddressLocality(),
                'postalCode' => $address->getPostalCode(),
                'addressLines' => [$address->getStreetAddress()],
            ],
            'locationName' => '',
            'actualStartDateTime' => $actionTime->format(\DateTimeInterface::ATOM),
            'actualEndDateTime' => $isDropoff
                ? $actionTime->modify('+5 minutes')->format(\DateTimeInterface::ATOM)
                : $actionTime->format(\DateTimeInterface::ATOM),
            'action' => [
                'actionName' => $isDropoff ? 'Déchargement' : 'Chargement',
                'actionState' => ActionState::ACTUAL->value,
                'actionType' => 'HANDLING',
                'actionSubtype' => $actionType,
                'sequenceNumber' => 1,
            ],
        ];
    }
}
