<?php

namespace AppBundle\Api\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use AppBundle\Api\Resource\UrbantzWebhook;
use Doctrine\ORM\EntityManagerInterface;

final class UrbantzWebhookDataPersister implements ContextAwareDataPersisterInterface
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function supports($data, array $context = []): bool
    {
        return $data instanceof UrbantzWebhook;
    }

    public function persist($data, array $context = [])
    {
        foreach ($data->deliveries as $delivery) {
            $this->entityManager->persist($delivery);
        }

        $this->entityManager->flush();

        return $data;
    }

    public function remove($data, array $context = [])
    {
        // call your persistence layer to delete $data
    }
}
