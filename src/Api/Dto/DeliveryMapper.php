<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Task;
use AppBundle\Service\TagManager;
use AppBundle\Sylius\Order\OrderInterface;
use Hashids\Hashids;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryMapper
{

    public function __construct(
        private readonly TaskMapper $taskMapper,
        private readonly TagManager $tagManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Hashids $hashids8,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public function map(
        Delivery $deliveryEntity,
        ?OrderInterface $order,
        ?ArbitraryPrice $arbitraryPrice,
        bool $isSavedOrder
    ): DeliveryDto {
        $deliveryData = new DeliveryDto();

        $tasks = $deliveryEntity->getTasks();

        $deliveryData->tasks = array_map(function (Task $taskEntity) use ($tasks) {
            $taskData = new TaskDto();

            $taskData->id = $taskEntity->getId();
            $taskData->createdAt = $taskEntity->getCreatedAt();
            $taskData->updatedAt = $taskEntity->getUpdatedAt();

            $taskData->type = $taskEntity->getType();
            $taskData->status = $taskEntity->getStatus();

            $taskData->address = $this->normalizer->normalize($taskEntity->getAddress(), 'jsonld');
            // Workaround to properly normalize embedded relation
            // Should become unnecessary when we normalise address using built-in normalizer
            // See a comment at TaskDto Address property
            if (isset($taskData->address['@context'])) {
                unset($taskData->address['@context']);
            }

            $taskData->after = $taskEntity->getAfter();
            $taskData->before = $taskEntity->getBefore();
            $taskData->doneAfter = $taskEntity->getAfter();
            $taskData->doneBefore = $taskEntity->getBefore();

            $taskData->comments = $taskEntity->getComments();
            $taskData->tags = $this->tagManager->expand($taskEntity->getTags());
            $taskData->weight = $this->taskMapper->getWeight($taskEntity, $tasks);
            $taskData->packages = $this->taskMapper->getPackages($taskEntity, $tasks);
            $taskData->metadata = $taskEntity->getMetadata();

            return $taskData;
        }, $deliveryEntity->getTasks());

        $deliveryData->pickup = $deliveryData->tasks[0] ?? null;
        $deliveryData->dropoff = end($deliveryData->tasks) ?: null;

        $deliveryOrderData = new DeliveryOrderDto();
        $deliveryData->order = $deliveryOrderData;

        if (!is_null($order?->getId())) {
            $deliveryOrderData->id = $order->getId();
        }

        $deliveryOrderData->arbitraryPrice = $arbitraryPrice ? new ArbitraryPriceDto(
            $arbitraryPrice->getValue(),
            $arbitraryPrice->getVariantName()
        ) : null;

        $deliveryOrderData->isSavedOrder = $isSavedOrder;

        if ($deliveryEntity->getId()) {
            $deliveryData->id = $deliveryEntity->getId();

            $deliveryData->trackingUrl = $this->urlGenerator->generate('public_delivery', [
                'hashid' => $this->hashids8->encode($deliveryEntity->getId()),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $deliveryData;
    }
}
