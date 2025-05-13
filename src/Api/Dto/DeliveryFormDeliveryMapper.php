<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Task;
use Hashids\Hashids;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DeliveryFormDeliveryMapper
{

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Hashids $hashids8,
    ) {
    }

    public function map(
        Delivery $deliveryEntity,
        ?ArbitraryPrice $arbitraryPrice
    ): DeliveryFormDeliveryOutput {
        $deliveryData = new DeliveryFormDeliveryOutput();

        $deliveryData->tasks = array_map(function (Task $taskEntity) {
            $taskData = new DeliveryFormTaskOutput();

            $taskData->id = $taskEntity->getId();
            $taskData->type = $taskEntity->getType();
            $taskData->address = $taskEntity->getAddress();
            $taskData->after = $taskEntity->getAfter();
            $taskData->before = $taskEntity->getBefore();
            $taskData->comments = $taskEntity->getComments();
            $taskData->tags = $taskEntity->getTags();
            $taskData->weight = $taskEntity->getWeight();
            $taskData->packages = $taskEntity->getPackages()->toArray();
            $taskData->metadata = $taskEntity->getMetadata();

            return $taskData;
        }, $deliveryEntity->getTasks());

        $deliveryData->arbitraryPrice = $arbitraryPrice;

        if ($deliveryEntity->getId()) {
            $deliveryData->id = $deliveryEntity->getId();

            $deliveryData->trackingUrl = $this->urlGenerator->generate('public_delivery', [
                'hashid' => $this->hashids8->encode($deliveryEntity->getId()),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $deliveryData;
    }
}
