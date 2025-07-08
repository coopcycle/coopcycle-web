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
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DeliveryMapper
{

    public function __construct(
        private readonly TaskMapper $taskMapper,
        private readonly TagManager $tagManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Hashids $hashids8,
        private readonly NormalizerInterface $normalizer,
        private readonly ObjectNormalizer $symfonyNormalizer
    ) {
    }

    public function map(
        Delivery $deliveryEntity,
        ?OrderInterface $order,
        ?ArbitraryPrice $arbitraryPrice,
        bool $isSavedOrder,
        array $groups = []
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

            $address = $taskEntity->getAddress();
            if ($address->getId() !== null) {
                $taskData->address = $this->normalizer->normalize($address, 'jsonld', $groups);
                // Workaround to properly normalize embedded relation
                // Should become unnecessary when we normalise address using built-in normalizer
                // See a comment at TaskDto Address property
                if (isset($taskData->address['@context'])) {
                    unset($taskData->address['@context']);
                }
            } else {
                // a case when address doesn't have an ID (for example, in Recurrence rules)
                // (we can't use API platform normalizer here, as it fails with: Unable to generate an IRI for the item)
                $taskData->address = $this->symfonyNormalizer->normalize($address, 'json', $groups);
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

            if (!is_null($taskEntity->getId())) {
                $taskData->barcode = $this->taskMapper->getBarcode($taskEntity);
            }

            return $taskData;
        }, $deliveryEntity->getTasks());

        $deliveryData->pickup = $deliveryData->tasks[0] ?? null;
        $deliveryData->dropoff = end($deliveryData->tasks) ?: null;

        $deliveryOrderData = new DeliveryOrderDto();
        $deliveryData->order = $deliveryOrderData;

        if (!is_null($order)) {
            if (!is_null($order->getId())) {
                $deliveryOrderData->id = $order->getId();
            }

            $deliveryOrderData->total = $order->getTotal();
            $deliveryOrderData->taxTotal = $order->getTaxTotal();
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
