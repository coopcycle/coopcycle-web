<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\RelayInput;
use AppBundle\Entity\Task;
use AppBundle\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class WarehouseRelayProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly EntityManagerInterface $entityManager,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * @param RelayInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        /** @var Warehouse */
        $warehouse = $this->provider->provide($operation, $uriVariables, $context);

        if (empty($data->tasks)) {
            throw new BadRequestHttpException('tasks must not be empty');
        }

        // The caller only needs to select a single task (a pickup *or* a dropoff): we
        // resolve each selected task to its (pickup, dropoff) pair here. Selecting the
        // pickup, its dropoff, or both must yield a single relay operation, so pairs are
        // deduplicated by their pickup.
        $pairs = [];
        foreach ($data->tasks as $task) {
            [$pickup, $dropoff] = $this->resolvePair($task);

            if (!$pickup || !$dropoff) {
                throw new BadRequestHttpException(
                    sprintf('Could not find a linked pickup/dropoff pair for task #%d', $task->getId())
                );
            }

            $pairs[spl_object_id($pickup)] = [$pickup, $dropoff];
        }

        $tasks = [];
        foreach ($pairs as [$pickup, $dropoff]) {
            [$hubDropoff, $hubPickup] = $this->relayThroughWarehouse($warehouse, $pickup, $dropoff);
            // Return both the original tasks (their previous-chain has changed) and the
            // tasks created by the relay operation.
            array_push($tasks, $pickup, $dropoff, $hubDropoff, $hubPickup);
        }

        $this->entityManager->flush();

        $groups = ['task', 'delivery', 'address'];

        return new JsonResponse([
            'tasks' => array_map(
                fn(Task $t) => $this->normalizer->normalize($t, 'jsonld', ['groups' => $groups]),
                $tasks
            ),
        ], 201);
    }

    /**
     * Resolve any single task to the (pickup, dropoff) pair it belongs to.
     *
     * @return array{0: ?Task, 1: ?Task}
     */
    private function resolvePair(Task $task): array
    {
        $delivery = $task->getDelivery();
        if ($delivery !== null) {
            return [$delivery->getPickup(), $delivery->getDropoff()];
        }

        // Standalone tasks are linked through the previous/next chain.
        if ($task->isDropoff()) {
            return [$task->getPrevious(), $task];
        }

        $dropoff = $task->getNext()
            ?? $this->entityManager->getRepository(Task::class)->findOneBy(['previous' => $task]);

        return [$task, $dropoff];
    }

    /**
     * Create the two hub tasks that relay a pickup → dropoff through the warehouse.
     *
     * @return array{0: Task, 1: Task} The created [hubDropoff, hubPickup]
     */
    private function relayThroughWarehouse(Warehouse $warehouse, Task $pickupTask, Task $dropoffTask): array
    {
        $warehouseAddress = $warehouse->getAddress();

        // Hub tasks share a time window that places them visually between the originals.
        // When there is a genuine gap between pickup end and dropoff start, use that gap.
        // When the windows overlap or are equal, fall back to the pickup's window; the
        // previous-chain and delivery_position then handle display ordering.
        if ($pickupTask->getDoneBefore() < $dropoffTask->getDoneAfter()) {
            $hubWindowAfter  = $pickupTask->getDoneBefore();
            $hubWindowBefore = $dropoffTask->getDoneAfter();
        } else {
            $hubWindowAfter  = $pickupTask->getDoneAfter();
            $hubWindowBefore = $pickupTask->getDoneBefore();
        }

        // Drop at hub
        $hubDropoff = new Task();
        $hubDropoff->setType(Task::TYPE_DROPOFF);
        $hubDropoff->setAddress($warehouseAddress);
        $hubDropoff->setDoneAfter($hubWindowAfter);
        $hubDropoff->setDoneBefore($hubWindowBefore);
        $hubDropoff->setComments($pickupTask->getComments());
        $hubDropoff->setWeight($pickupTask->getWeight());
        $hubDropoff->setTags($pickupTask->getTags());
        foreach ($pickupTask->getPackages() as $pkg) {
            $hubDropoff->addPackageWithQuantity($pkg->getPackage(), $pkg->getQuantity());
        }

        // Pickup from hub
        $hubPickup = new Task();
        $hubPickup->setType(Task::TYPE_PICKUP);
        $hubPickup->setAddress($warehouseAddress);
        $hubPickup->setDoneAfter($hubWindowAfter);
        $hubPickup->setDoneBefore($hubWindowBefore);
        $hubPickup->setComments($dropoffTask->getComments());
        $hubPickup->setWeight($dropoffTask->getWeight());
        $hubPickup->setTags($dropoffTask->getTags());
        foreach ($dropoffTask->getPackages() as $pkg) {
            $hubPickup->addPackageWithQuantity($pkg->getPackage(), $pkg->getQuantity());
        }

        // Establish the logical chain: pickup → hubDropoff → hubPickup → dropoff
        $hubDropoff->setPrevious($pickupTask);
        $hubPickup->setPrevious($hubDropoff);
        if (!$dropoffTask->hasPrevious() || $dropoffTask->getPrevious() === $pickupTask) {
            $dropoffTask->setPrevious($hubPickup);
        }

        // If the original tasks belong to a delivery, insert hub tasks into it
        $delivery = $pickupTask->getDelivery() ?? $dropoffTask->getDelivery();
        if ($delivery !== null) {
            $pickupPosition = $delivery->findTaskPosition($pickupTask);
            $delivery->addTask($hubDropoff, $pickupPosition + 1);
            $delivery->addTask($hubPickup, $pickupPosition + 2);
            // Refresh all delivery_position metadata to reflect new positions
            foreach ($delivery->getItems() as $item) {
                $item->getTask()->setMetadata('delivery_position', $item->getPosition() + 1);
            }
        }

        $this->entityManager->persist($hubDropoff);
        $this->entityManager->persist($hubPickup);

        return [$hubDropoff, $hubPickup];
    }
}
