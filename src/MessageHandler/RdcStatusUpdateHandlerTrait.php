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
        $serviceId = (string) $delivery->getId();
        $actionTime = $message->actionTime ?? new \DateTimeImmutable();

        if ($task->getType() === Task::TYPE_DROPOFF) {
            $activityId = $this->getActivityId((string) $delivery->getPickup()->getId());
        } else {
            $activityId = $this->getActivityId((string) $message->taskId);
        }

        $address = $task->getAddress();
        $location = $this->buildLocationUpdate($address, $config['actionType'], $actionTime);

        if ($message->coopcycleStatus === Task::STATUS_DOING) {
            return;
        }

        if ($message->coopcycleStatus === Task::STATUS_DONE) {
            if ($config['shouldPatch']) {
                $this->patchService($rdcClient, $serviceId, ExecutionStatus::FINISHED->value, $location, true);
                $this->patchActivity($rdcClient, $activityId, ExecutionStatus::FINISHED->value, $location, true);
            }

            foreach ($config['serviceEvents'] as $event) {
                $this->postServiceEvent(
                    $rdcClient,
                    $serviceId,
                    $event['code'],
                    $event['type'],
                    $location,
                    sprintf($event['description'], $serviceId),
                    $actionTime
                );
            }

            foreach ($config['activityEvents'] as $event) {
                $this->postActivityEvent(
                    $rdcClient,
                    $activityId,
                    $event['code'],
                    $event['type'],
                    $location,
                    sprintf($event['description'], $activityId),
                    $actionTime
                );
            }
        }

        $this->logger->info('Processed RDC status update', [
            'task_id' => $message->taskId,
            'service_id' => $serviceId,
            'status' => $message->coopcycleStatus,
        ]);
    }

    private function patchService(RdcClientInterface $rdcClient, string $serviceId, string $executionStatus, array $location, bool $isEndLocation = false): void
    {
        $payload = $isEndLocation
            ? ['executionStatus' => $executionStatus, 'endLocation' => $location]
            : ['executionStatus' => $executionStatus, 'startLocation' => $location];

        $rdcClient->patch(sprintf('/services/%s', $serviceId), $payload);
    }

    private function patchActivity(RdcClientInterface $rdcClient, string $activityId, string $executionStatus, array $location, bool $isEndLocation = false): void
    {
        $payload = $isEndLocation
            ? ['executionStatus' => $executionStatus, 'endLocation' => $location]
            : ['executionStatus' => $executionStatus, 'startLocation' => $location];

        $rdcClient->patch(sprintf('/activities/%s', $activityId), $payload);
    }

    private function postServiceEvent(
        RdcClientInterface $rdcClient,
        string $serviceId,
        EventCode $code,
        EventType $eventType,
        array $location,
        string $description,
        \DateTimeImmutable $actionTime
    ): void {
        $rdcClient->post(sprintf('/services/%s/events', $serviceId), [
            'eventType' => $eventType->value,
            'code' => $code->value,
            'domain' => EventDomain::BUSINESS->value,
            'description' => $description,
            'location' => $location,
            'actualDateTime' => $actionTime->format(\DateTimeInterface::ATOM),
            'creationDateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function postActivityEvent(
        RdcClientInterface $rdcClient,
        string $activityId,
        EventCode $code,
        EventType $eventType,
        array $location,
        string $description,
        \DateTimeImmutable $actionTime
    ): void {
        $rdcClient->post(sprintf('/activities/%s/events', $activityId), [
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

    private function buildLocationUpdate(Address $address, string $actionType, \DateTimeImmutable $actionTime): array
    {
        return [
            'address' => [
                'addressCountry' => ['countryCode' => 'FR', 'countryName' => 'France'],
                'addressLocality' => $address->getAddressLocality(),
                'postalCode' => $address->getPostalCode(),
                'addressLines' => [$address->getStreetAddress()],
            ],
            'locationName' => '',
            'actualStartDateTime' => $actionTime->format(\DateTimeInterface::ATOM),
            'actualEndDateTime' => $actionTime->format(\DateTimeInterface::ATOM),
            'action' => [
                'actionName' => $actionType === 'UNLOADING' ? 'Déchargement' : 'Chargement',
                'actionState' => ActionState::ACTUAL->value,
                'actionType' => 'HANDLING',
                'actionSubtype' => $actionType,
                'sequenceNumber' => 1,
            ],
        ];
    }
}