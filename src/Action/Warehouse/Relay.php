<?php

namespace AppBundle\Action\Warehouse;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Api\Dto\RelayInput;
use AppBundle\Entity\Task;
use AppBundle\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class Relay
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly EntityManagerInterface $entityManager,
        private readonly NormalizerInterface $normalizer,
    ) {}

    public function __invoke(Warehouse $data, RelayInput $input): JsonResponse
    {
        $tasks = array_map(
            fn($iri) => $this->iriConverter->getResourceFromIri($iri),
            $input->tasks
        );

        $pickupTask  = current(array_filter($tasks, fn($t) => $t instanceof Task && $t->isPickup()));
        $dropoffTask = current(array_filter($tasks, fn($t) => $t instanceof Task && $t->isDropoff()));

        if (!$pickupTask || !$dropoffTask) {
            throw new BadRequestHttpException('tasks must contain exactly one PICKUP and one DROPOFF');
        }

        $warehouseAddress = $data->getAddress();

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
