<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use Hashids\Hashids;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DeliveryFormDeliveryMapper
{

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Hashids $hashids8,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function map(
        Delivery $deliveryEntity,
        ?OrderInterface $order,
        ?ArbitraryPrice $arbitraryPrice,
        bool $isSavedOrder
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
            $taskData->packages = array_map(function (Task\Package $taskPackage) {
                $packageData = new DeliveryFormTaskPackageDto();
                $packageData->type = $taskPackage->getPackage()->getName();
                $packageData->quantity = $taskPackage->getQuantity();
                return $packageData;
            }, $taskEntity->getPackages()->toArray());
            $taskData->metadata = $taskEntity->getMetadata();

            return $taskData;
        }, $deliveryEntity->getTasks());

        $deliveryData->pickup = $deliveryData->tasks[0] ?? null;
        $deliveryData->dropoff = end($deliveryData->tasks) ?: null;

        $deliveryData->arbitraryPrice = $arbitraryPrice ? new ArbitraryPriceDto(
            $arbitraryPrice->getValue(),
            $arbitraryPrice->getVariantName()
        ) : null;

        $deliveryData->isSavedOrder = $isSavedOrder;

        if ($deliveryEntity->getId()) {
            $deliveryData->id = $deliveryEntity->getId();

            $deliveryData->trackingUrl = $this->urlGenerator->generate('public_delivery', [
                'hashid' => $this->hashids8->encode($deliveryEntity->getId()),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        if (!is_null($order)) {
            $deliveryData->order = $this->iriConverter->getIriFromResource($order);
        }

        return $deliveryData;
    }
}
