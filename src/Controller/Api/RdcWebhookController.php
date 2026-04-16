<?php

declare(strict_types=1);

namespace AppBundle\Controller\Api;

use AppBundle\Integration\Rdc\Webhook\RdcIdempotencyChecker;
use AppBundle\Integration\Rdc\Webhook\WebhookPayloadParser;
use AppBundle\Message\RdcWebhookMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class RdcWebhookController extends AbstractController
{
    private const WEBHOOK_SOURCE = 'RDC';

    public function __construct(
        private readonly WebhookPayloadParser $payloadParser,
        private readonly RdcIdempotencyChecker $idempotencyChecker,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly string $rdcWebhookSecret,
    ) {
    }

    #[Route(path: '/api/v1/webhooks/rdc', name: 'api_v1_webhooks_rdc', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        // Validate X-webhook-source header
        $source = $request->headers->get('X-webhook-source');
        if ($source !== self::WEBHOOK_SOURCE) {
            $this->logger->warning('RDC webhook received with invalid source', [
                'source' => $source,
            ]);
            return new JsonResponse(['error' => 'Invalid webhook source'], Response::HTTP_FORBIDDEN);
        }

        // Validate X-webhook-secret header with timing-attack safe comparison
        $secret = $request->headers->get('X-webhook-Secret');
        if (!hash_equals($this->rdcWebhookSecret, $secret)) {
            $this->logger->warning('RDC webhook received with invalid secret');
            return new JsonResponse(['error' => 'Invalid webhook secret'], Response::HTTP_FORBIDDEN);
        }

        // Parse JSON payload
        $content = $request->getContent();
        $payload = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('RDC webhook received with invalid JSON', [
                'json_error' => json_last_error_msg(),
            ]);
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        // Parse webhook payload
        $parsed = $this->payloadParser->parse($payload);
        if ($parsed === null) {
            return new JsonResponse(['error' => 'Invalid payload structure'], Response::HTTP_BAD_REQUEST);
        }

        $loUri = $parsed['loUri'];
        $eventType = $parsed['eventType'];
        $lo = $parsed['lo'];

        // Check idempotency and resolve effective event type
        $effectiveEventType = $this->idempotencyChecker->resolveEventType($loUri, $eventType);

        // Mark as processed (will be idempotent due to unique constraint)
        if (!$this->idempotencyChecker->isAlreadyProcessed($loUri)) {
            $this->idempotencyChecker->markAsProcessed($loUri, $effectiveEventType);
        }

        // Dispatch message to queue
        $message = new RdcWebhookMessage(
            $loUri,
            $effectiveEventType,
            $lo,
            new \DateTimeImmutable(),
        );
        $this->messageBus->dispatch($message);

        $this->logger->info('RDC webhook accepted', [
            'lo_uri' => $loUri,
            'event_type' => $eventType,
            'effective_event_type' => $effectiveEventType,
        ]);

        return new JsonResponse([
            'status' => 'accepted',
            'lo_uri' => $loUri,
            'event_type' => $effectiveEventType,
        ]);
    }
}
