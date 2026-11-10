<?php

declare(strict_types=1);

namespace AppBundle\Controller\Api;

use AppBundle\Message\RdcMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class RdcWebhookController extends AbstractController
{
    private const WEBHOOK_SOURCE_HEADER = 'X-webhook-Source';
    private const WEBHOOK_SECRET_HEADER = 'X-webhook-Secret';

    // The only object we handle: created, it becomes a delivery;
    // updated, the requestor may have cancelled it.
    private const SUPPORTED_RESOURCE_TYPE = 'servicerequest';
    private const SUPPORTED_TRIGGER_TYPES = ['create', 'update'];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
        private readonly array $rdcConnections = [],
        private readonly bool $rdcEnabled = false,
    ) {}

    #[Route(path: '/api/v1/webhooks/rdc', name: 'api_v1_webhooks_rdc', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        if (!$this->rdcEnabled) {
            return $this->errorResponse('Not Found', Response::HTTP_NOT_FOUND);
        }

        // The source tells which connection sends the webhook, hence which secret it must know
        $source = (string) $request->headers->get(self::WEBHOOK_SOURCE_HEADER, '');
        if (!array_key_exists($source, $this->rdcConnections)) {
            $this->logger->warning('RDC webhook request from an unknown source', [
                'source' => $source,
                'known_sources' => array_keys($this->rdcConnections),
            ]);
            return $this->errorResponse('Invalid BOL member', Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->isValidSecret($request, $source)) {
            return $this->errorResponse('Invalid webhook secret', Response::HTTP_UNAUTHORIZED);
        }

        $events = $this->parsePayload($request);
        if (empty($events)) {
            return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
        }

        $results = [];
        foreach ($events as $event) {
            $results[] = $this->handleEvent($event, $source);
        }

        return new JsonResponse(['results' => $results], Response::HTTP_ACCEPTED);
    }

    private function handleEvent(mixed $event, string $source): array
    {
        $metadata = is_array($event) ? ($event['notificationMetadata'] ?? null) : null;
        $lo = is_array($event) ? ($event['lo'] ?? null) : null;

        if (!is_array($metadata) || !is_array($lo)
            || !is_string($metadata['loUri'] ?? null)
            || !is_string($metadata['notificationType'] ?? null)) {
            return $this->rejectEvent('Missing required metadata', $source, is_array($metadata) ? $metadata : []);
        }

        $resourceType = $this->lowercase($metadata['resourceType'] ?? null);
        $triggerType = $this->lowercase($metadata['triggerType'] ?? null);

        if ($resourceType !== self::SUPPORTED_RESOURCE_TYPE) {
            return $this->rejectEvent('Unsupported resource type', $source, $metadata);
        }
        if (!in_array($triggerType, self::SUPPORTED_TRIGGER_TYPES, true)) {
            $this->logger->info('RDC webhook event skipped', $this->eventContext($source, $metadata));

            return $this->buildResponse('skipped', $metadata, $resourceType, $triggerType);
        }

        $this->messageBus->dispatch(new RdcMessage(
            loPayload: $lo,
            loMember: $source,
            loUri: $metadata['loUri'],
            loRevision: is_numeric($metadata['loRevision'] ?? null) ? (int) $metadata['loRevision'] : null,
            notificationMetadata: $metadata,
        ));

        return $this->buildResponse('queued', $metadata, $resourceType, $triggerType);
    }

    private function parsePayload(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('RDC webhook payload is not valid JSON', ['error' => json_last_error_msg()]);
            return null;
        }
        if (!is_array($payload) || !array_is_list($payload) || empty($payload)) {
            $this->logger->warning('RDC webhook payload is not a list of events');
            return null;
        }
        return $payload;
    }

    private function isValidSecret(Request $request, string $source): bool
    {
        $configuredSecret = (string) ($this->rdcConnections[$source]['webhookSecret'] ?? '');
        // Without it, an empty header would match
        if ($configuredSecret === '') {
            $this->logger->error('RDC connection has no webhook secret, its webhooks are refused', [
                'source' => $source,
            ]);
            return false;
        }

        $providedSecret = (string) $request->headers->get(self::WEBHOOK_SECRET_HEADER, '');
        if (!hash_equals($configuredSecret, $providedSecret)) {
            $this->logger->warning('RDC webhook request with invalid secret', [
                'source' => $source,
            ]);
            return false;
        }

        return true;
    }

    private function lowercase(mixed $value): string
    {
        return is_string($value) ? strtolower($value) : '';
    }

    /**
     * The BOL reads a 202 as delivered and won't send the event again: log
     * why it is dropped.
     */
    private function rejectEvent(string $reason, string $source, array $metadata): array
    {
        $this->logger->warning(sprintf('RDC webhook event rejected: %s', $reason), $this->eventContext($source, $metadata));

        return ['status' => 'error', 'error' => $reason];
    }

    private function eventContext(string $source, array $metadata): array
    {
        return [
            'source' => $source,
            'lo_uri' => is_string($metadata['loUri'] ?? null) ? $metadata['loUri'] : null,
            'resource_type' => $metadata['resourceType'] ?? null,
            'trigger_type' => $metadata['triggerType'] ?? null,
            'revision' => $metadata['loRevision'] ?? null,
        ];
    }

    private function buildResponse(string $status, array $metadata, string $resourceType, string $triggerType): array
    {
        return [
            'status' => $status,
            'lo_uri' => $metadata['loUri'],
            'event_type' => $metadata['notificationType'],
            'trigger_type' => $triggerType,
            'resource_type' => $resourceType,
            'revision' => $metadata['loRevision'] ?? null,
        ];
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
