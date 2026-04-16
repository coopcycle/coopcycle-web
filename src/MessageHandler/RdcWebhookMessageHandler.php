<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Message\RdcWebhookMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RdcWebhookMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RdcWebhookMessage $message): void
    {
        $this->logger->info('RDC webhook message received', [
            'lo_uri' => $message->getLoUri(),
            'event_type' => $message->getEventType(),
            'received_at' => $message->getReceivedAt()->format(\DateTimeInterface::ATOM),
        ]);

        // TODO Iteration 3: Implement the actual webhook processing logic
        // - Based on event type (create/update/delete), dispatch to appropriate service
        // - Create or update delivery/order/task in Coopcycle
        // - Handle different lo types (shipments, orders, etc.)
        // - Return appropriate status
    }
}
