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

        $pickupTask  = current(array_filter($data->tasks, fn(Task $t) => $t->isPickup()));
        $dropoffTask = current(array_filter($data->tasks, fn(Task $t) => $t->isDropoff()));

        if (!$pickupTask || !$dropoffTask) {
            throw new BadRequestHttpException('tasks must contain exactly one PICKUP and one DROPOFF');
        }

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
        $this->entityManager->flush();

        $groups = ['task', 'delivery', 'address'];

        return new JsonResponse([
            'hubDropoff' => $this->normalizer->normalize($hubDropoff, 'jsonld', ['groups' => $groups]),
            'hubPickup'  => $this->normalizer->normalize($hubPickup,  'jsonld', ['groups' => $groups]),
        ], 201);
    }
}
