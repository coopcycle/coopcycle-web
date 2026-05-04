<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Integration\Rdc\Coopcycle\InternalDeliveryCreator;
use AppBundle\Integration\Rdc\Mapper\ServiceRequestMapper;
use AppBundle\Integration\Rdc\RdcStoreResolver;
use AppBundle\Message\RdcWebhookMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RdcWebhookMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ServiceRequestMapper $serviceRequestMapper,
        private readonly InternalDeliveryCreator $deliveryCreator,
        private readonly RdcStoreResolver $storeResolver,
    ) {
    }

    public function __invoke(RdcWebhookMessage $message): void
    {
        $this->logger->info('RDC webhook message received', [
            'lo_uri' => $message->getLoUri(),
            'event_type' => $message->getEventType(),
            'received_at' => $message->getReceivedAt()->format(\DateTimeInterface::ATOM),
        ]);

        $payload = $message->getPayload();
        $serviceRequest = $payload['lo'] ?? null;

        if ($serviceRequest === null) {
            $this->logger->warning('RDC webhook payload missing lo field');
            return;
        }

        // TODO Iteration 4: Handle update/cancel events
        if ($message->getEventType() !== 'create') {
            $this->logger->info('RDC webhook event type not yet handled', [
                'event_type' => $message->getEventType(),
            ]);
            return;
        }

        try {
            // Resolve the store for this RDC integration
            $store = $this->storeResolver->resolveStore();
            if ($store === null) {
                throw new \RuntimeException('No store resolved for RDC webhook');
            }

            // Map the RDC service request to array structure
            $orderData = $this->serviceRequestMapper->map($serviceRequest);

            // Create the delivery and order using internal services
            $delivery = $this->deliveryCreator->createDeliveryWithOrder(
                $orderData,
                $store,
                $message->getLoUri()
            );

            $this->logger->info('Coopcycle delivery created from RDC webhook', [
                'lo_uri' => $message->getLoUri(),
                'delivery_id' => $delivery->getId(),
                'order_number' => $delivery->getOrder()?->getNumber(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create Coopcycle delivery from RDC webhook', [
                'lo_uri' => $message->getLoUri(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
