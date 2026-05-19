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

        // Drop at hub: copies pickup's time window
        $hubDropoff = new Task();
        $hubDropoff->setType(Task::TYPE_DROPOFF);
        $hubDropoff->setAddress($warehouseAddress);
        $hubDropoff->setDoneAfter($pickupTask->getDoneAfter());
        $hubDropoff->setDoneBefore($pickupTask->getDoneBefore());
        $hubDropoff->setComments($pickupTask->getComments());
        $hubDropoff->setWeight($pickupTask->getWeight());
        foreach ($pickupTask->getPackages() as $pkg) {
            $hubDropoff->addPackageWithQuantity($pkg->getPackage(), $pkg->getQuantity());
        }

        // Pickup from hub: copies dropoff's time window
        $hubPickup = new Task();
        $hubPickup->setType(Task::TYPE_PICKUP);
        $hubPickup->setAddress($warehouseAddress);
        $hubPickup->setDoneAfter($dropoffTask->getDoneAfter());
        $hubPickup->setDoneBefore($dropoffTask->getDoneBefore());
        $hubPickup->setComments($dropoffTask->getComments());
        $hubPickup->setWeight($dropoffTask->getWeight());
        foreach ($dropoffTask->getPackages() as $pkg) {
            $hubPickup->addPackageWithQuantity($pkg->getPackage(), $pkg->getQuantity());
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
