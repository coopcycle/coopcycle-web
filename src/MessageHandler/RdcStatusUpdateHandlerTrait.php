<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\Api\RdcClientFactory;
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

        match ($message->coopcycleStatus) {
            Task::STATUS_DOING => $this->handleStatusDoing($rdcClient, $serviceId, $activityId, $location, $actionTime, $config),
            Task::STATUS_DONE => $this->handleStatusDone($rdcClient, $serviceId, $activityId, $location, $actionTime, $config),
            Task::STATUS_CANCELLED => $this->handleStatusCancelled($rdcClient, $serviceId, $activityId, $location, $actionTime, $config),
            default => null,
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
        array $config
    ): void {
        if ($config['shouldPatch'] ?? false) {
            $this->patchExecution($rdcClient, 'services', $serviceId, ExecutionStatus::STARTED->value, $location, false);
            $this->patchExecution($rdcClient, 'activities', $activityId, ExecutionStatus::STARTED->value, $location, false);
        }

        $this->postEvents($rdcClient, 'services', $serviceId, $config['startServiceEvents'] ?? [], $location, $actionTime);
        $this->postEvents($rdcClient, 'activities', $activityId, $config['startActivityEvents'] ?? [], $location, $actionTime);
    }

    private function handleStatusDone(
        RdcClientInterface $rdcClient,
        string $serviceId,
        string $activityId,
        array $location,
        \DateTimeImmutable $actionTime,
        array $config
    ): void {
        if ($config['shouldPatch'] ?? false) {
            $this->patchExecution($rdcClient, 'services', $serviceId, ExecutionStatus::FINISHED->value, $location, true);
            $this->patchExecution($rdcClient, 'activities', $activityId, ExecutionStatus::FINISHED->value, $location, true);
        }

        $this->postEvents($rdcClient, 'services', $serviceId, $config['serviceEvents'] ?? [], $location, $actionTime);
        $this->postEvents($rdcClient, 'activities', $activityId, $config['activityEvents'] ?? [], $location, $actionTime);
    }

    private function handleStatusCancelled(
        RdcClientInterface $rdcClient,
        string $serviceId,
        string $activityId,
        array $location,
        \DateTimeImmutable $actionTime,
        array $config
    ): void {
        if ($config['shouldPatch'] ?? false) {
            $this->patchExecution($rdcClient, 'services', $serviceId, ExecutionStatus::CANCELLED->value, $location, true);
            $this->patchExecution($rdcClient, 'activities', $activityId, ExecutionStatus::CANCELLED->value, $location, true);
            $this->patchServiceStatus($rdcClient, $serviceId, ServiceStatus::CANCELLED);
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

    private function patchServiceStatus(RdcClientInterface $rdcClient, string $serviceId, ServiceStatus $status): void
    {
        $rdcClient->patch(sprintf('/services/%s', $serviceId), [
            'status' => $status->value,
        ]);
    }

    private function postEvents(
        RdcClientInterface $rdcClient,
        string $resourceType,
        string $resourceId,
        array $events,
        array $location,
        \DateTimeImmutable $actionTime
    ): void {
        foreach ($events as $event) {
            $this->postExecutionEvent(
                $rdcClient,
                $resourceType,
                $resourceId,
                $event['code'],
                $event['type'],
                $location,
                sprintf($event['description'], $resourceId),
                $actionTime
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
        \DateTimeImmutable $actionTime
    ): void {
        $rdcClient->post(sprintf('/%s/%s/events', $resourceType, $resourceId), [
            'eventType' => $eventType->value,
            'code' => $code->value,
            'domain' => EventDomain::BUSINESS->value,
            'description' => $description,
            'location' => $location,
            'actualDateTime' => $actionTime->format(\DateTimeInterface::ATOM),
            'creationDateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
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