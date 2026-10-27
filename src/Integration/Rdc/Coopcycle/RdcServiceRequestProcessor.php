<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc\Coopcycle;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
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
        // When a BOL call failed, Messenger retries the whole message: reuse the
        // delivery saved by the previous attempt instead of creating another one.
        $delivery = $this->findDelivery($originNodeUri ?? $apiRequest->getUri());
        if (is_null($delivery)) {
            $delivery = $this->createDelivery($apiRequest, $store, $originNodeUri);
        } else {
            $this->logger->info('Resuming RDC delivery from a previous attempt', [
                'delivery_id' => $delivery->getId(),
                'lo_uri' => $apiRequest->getUri(),
            ]);
        }

        $serviceFacade = $this->resolveServiceFacade($store);

        try {
            $pickupAddress = $this->mapper->mapPickupAddress($apiRequest);
            $dropoffAddress = $this->mapper->mapDropoffAddress($apiRequest);
            $pickupTimeSlot = $this->mapper->mapTimeSlot($apiRequest->getPickupTimeSlot());
            $dropoffTimeSlot = $this->mapper->mapTimeSlot($apiRequest->getDropoffTimeSlot());

            // Service and activity ids are deterministic, so creating them twice
            // would conflict: remember each one as soon as it exists.
            $serviceId = $delivery->getMetadata()['rdc']['service_id'] ?? null;
            if (is_null($serviceId)) {
                $serviceId = $serviceFacade->createService(
                    (string) $delivery->getId(),
                    $apiRequest,
                    $apiRequest->getBarcode(),
                    $apiRequest->getContractRef(),
                    $originNodeUri,
                    $pickupAddress,
                    $dropoffAddress,
                    $pickupTimeSlot,
                    $dropoffTimeSlot
                );
                $delivery->setMetadata('rdc.service_id', $serviceId);
                $this->entityManager->flush();
            }

            $activityId = $delivery->getMetadata()['rdc']['activity_id'] ?? null;
            if (is_null($activityId)) {
                $pickupTaskId = (string) $delivery->getPickup()->getId();
                $serviceUri = sprintf('%s/services/%s', $serviceFacade->getRdcClient()->getBaseUrl(), $serviceId);
                $activityId = $serviceFacade->createActivity(
                    $pickupTaskId,
                    $serviceUri,
                    $pickupAddress,
                    $dropoffAddress,
                    $pickupTimeSlot,
                    $dropoffTimeSlot
                );
                $delivery->setMetadata('rdc.activity_id', $activityId);
                $this->entityManager->flush();
            }

            $serviceFacade->linkActivityToService($serviceId, $activityId);

            $this->logger->info('BOL Service and Activity created', [
                'delivery_id' => $delivery->getId(),
                'service_id' => $serviceId,
                'activity_id' => $activityId,
            ]);

            $serviceFacade->notifyOriginNode(
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

    /**
     * The BOL calls must go through the connection the service request came
     * from, which is also the one status updates use (see RdcTaskContextResolver).
     */
    private function resolveServiceFacade(Store $store): RdcServiceFacade
    {
        $connectionId = $store->getRdcConnectionId();
        if (empty($connectionId)) {
            $this->logger->warning('Store has no RDC connection, using the default one', [
                'store_id' => $store->getId(),
            ]);

            return $this->serviceFacade;
        }

        return $this->serviceFacade->forConnection($connectionId);
    }

    private function findDelivery(?string $loUri): ?Delivery
    {
        if (empty($loUri)) {
            return null;
        }

        /** @var DeliveryRepository $repository */
        $repository = $this->entityManager->getRepository(Delivery::class);

        return $repository->findByLoUri($loUri);
    }

    private function createDelivery(RdcApiServiceRequest $apiRequest, Store $store, ?string $originNodeUri): Delivery
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

        return $delivery;
    }
}
