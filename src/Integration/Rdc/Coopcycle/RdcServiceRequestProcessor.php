<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\CalculateUsingPricingRules;
use AppBundle\Integration\Rdc\Api\RdcServiceFacade;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use AppBundle\Service\DeliveryOrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RdcServiceRequestProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryOrderManager $deliveryOrderManager,
        private readonly RdcServiceFacade $serviceFacade,
        private readonly RdcServiceRequestMapper $mapper,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(RdcApiServiceRequest $apiRequest, Store $store, ?string $originNodeUri = null, ?int $loRevision = null): Delivery
    {
        $delivery = $this->mapper->mapToDelivery($apiRequest, $store, $originNodeUri);

        $this->entityManager->persist($delivery);
        $this->deliveryOrderManager->createOrder($delivery, [
            'pricingStrategy' => new CalculateUsingPricingRules(),
            'persist' => true,
        ]);
        $this->entityManager->flush();

        $this->logger->info('Delivery and order created', [
            'delivery_id' => $delivery->getId(),
            'order_number' => $delivery->getOrder()?->getNumber(),
            'lo_uri' => $apiRequest->getUri(),
        ]);

        try {
            $serviceId = $this->serviceFacade->createService(
                (string) $delivery->getId(),
                $apiRequest,
                $apiRequest->getBarcode(),
                $apiRequest->getContractRef(),
                $originNodeUri,
                $this->mapper->mapPickupAddress($apiRequest),
                $this->mapper->mapDropoffAddress($apiRequest),
                $this->mapper->mapTimeSlot($apiRequest->getPickupTimeSlot()),
                $this->mapper->mapTimeSlot($apiRequest->getDropoffTimeSlot())
            );

            $pickupTaskId = (string) $delivery->getPickup()->getId();
            $serviceUri = sprintf('%s/services/%s', $this->serviceFacade->getRdcClient()->getBaseUrl(), $serviceId);
            $activityId = $this->serviceFacade->createActivity(
                $pickupTaskId,
                $serviceUri,
                $this->mapper->mapPickupAddress($apiRequest),
                $this->mapper->mapDropoffAddress($apiRequest),
                $this->mapper->mapTimeSlot($apiRequest->getPickupTimeSlot()),
                $this->mapper->mapTimeSlot($apiRequest->getDropoffTimeSlot())
            );

            $this->serviceFacade->linkActivityToService($serviceId, $activityId);

            $this->logger->info('BOL Service and Activity created', [
                'delivery_id' => $delivery->getId(),
                'service_id' => $serviceId,
                'activity_id' => $activityId,
            ]);

            $this->serviceFacade->notifyOriginNode(
                $originNodeUri ?? $apiRequest->getUri(),
                $serviceId,
                $apiRequest,
                $loRevision
            );

        } catch (\Throwable $e) {
            $this->logger->error('BOL operations failed after delivery save', [
                'delivery_id' => $delivery->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $delivery;
    }
}
