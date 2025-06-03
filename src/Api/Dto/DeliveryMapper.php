<?php

namespace AppBundle\Api\Dto;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use Hashids\Hashids;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryMapper
{

    public function __construct(
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

        $deliveryData->tasks = array_map(function (Task $taskEntity) {
            $taskData = new TaskDto();

            $taskData->id = $taskEntity->getId();
            $taskData->type = $taskEntity->getType();
            $taskData->address = $this->normalizer->normalize($taskEntity->getAddress(), 'jsonld');
            $taskData->after = $taskEntity->getAfter();
            $taskData->before = $taskEntity->getBefore();
            $taskData->comments = $taskEntity->getComments();
            $taskData->tags = $taskEntity->getTags();
            $taskData->weight = $taskEntity->getWeight();
            $taskData->packages = array_map(function (Task\Package $taskPackage) {
                $packageData = new TaskPackageDto();
                $packageData->type = $taskPackage->getPackage()->getName();
                $packageData->quantity = $taskPackage->getQuantity();
                return $packageData;
            }, $taskEntity->getPackages()->toArray());
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
