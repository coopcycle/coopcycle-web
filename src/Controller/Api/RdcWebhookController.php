<?php

declare(strict_types=1);

namespace AppBundle\Controller\Api;

use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestProcessor;
use AppBundle\Integration\Rdc\DTO\RdcApiActivity;
use AppBundle\Integration\Rdc\DTO\RdcApiService;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use AppBundle\Integration\Rdc\RdcStoreResolver;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RdcWebhookController extends AbstractController
{
    private const WEBHOOK_MEMBER_HEADER = 'X-webhook-Source';
    private const WEBHOOK_SECRET_HEADER = 'X-webhook-Secret';
    private const IDEMPOTENCY_TTL = 21600;
    private const CACHE_KEY_PREFIX = 'rdc_webhook_event:';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RdcServiceRequestProcessor $processor,
        private readonly RdcStoreResolver $storeResolver,
        private readonly Redis $redis,
    ) {}

    #[Route(path: '/api/v1/webhooks/rdc', name: 'api_v1_webhooks_rdc', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        if (!$this->isValidSecret($request)) {
            return new JsonResponse(['error' => 'Invalid webhook secret'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isValidBOLMember($request)) {
            return new JsonResponse(['error' => 'Invalid BOL member'], Response::HTTP_UNAUTHORIZED);
        }

        $events = $this->parsePayload($request);
        if (is_null($events)) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $results = [];
        foreach ($events as $event) {
            $results[] = $this->handleEvent($event, $request);
        }

        return new JsonResponse(['results' => $results], Response::HTTP_ACCEPTED);
    }

    private function handleEvent(array $event, Request $request): array
    {
        $metadata = $event['notificationMetadata'] ?? [];
        $lo = $event['lo'] ?? null;

        if ($this->isInvalidMetadata($lo, $metadata)) {
            return ['status' => 'error', 'error' => 'Missing required metadata'];
        }

        $eventHash = $this->hashEvent($metadata);
        if ($this->isDuplicateEvent($eventHash)) {
            $this->logger->info('RDC webhook event already processed', ['hash' => $eventHash]);
            return [
                'status' => 'duplicate',
                'hash' => $eventHash,
                'lo_uri' => $metadata['loUri'],
            ];
        }

        $dto = $this->parseDto($metadata['resourceType'] ?? null, $lo);
        if (is_null($dto)) {
            return ['status' => 'error', 'error' => 'Unsupported resource type'];
        }

        $loUri = $metadata['loUri'];
        $loMember = $request->headers->get(self::WEBHOOK_MEMBER_HEADER) ?? $metadata['loMemberIdentifier'];
        $eventType = $metadata['notificationType'];
        $loRevision = $metadata['loRevision'] ?? null;
        $triggerType = $metadata['triggerType'] ?? null;
        $resourceType = $metadata['resourceType'];

        if (strtolower($resourceType) !== 'servicerequest') {
            return $this->buildResult(
                $this->acceptedResponseArray($loUri, $eventType, $resourceType, $loRevision)
            );
        }

        if (strtolower((string) $triggerType) !== 'create') {
            return $this->buildResult(
                $this->acceptedResponseArray($loUri, $eventType, 'ServiceRequest', $loRevision)
            );
        }

        $store = $this->storeResolver->resolveStore($loMember);
        if (is_null($store)) {
            $this->logger->error('Store not found for RDC servicerequest', ['contract_ref' => $dto->getContractRef()]);
            return ['status' => 'error', 'error' => 'Store not found'];
        }

        $delivery = $this->processor->process($dto, $store, $loUri, $loRevision);

        return $this->buildResult(
            $this->acceptedResponseArray($loUri, $eventType, 'ServiceRequest', $loRevision, $delivery->getId())
        );
    }

    private function buildResult(array $accepted): array
    {
        return [
            'status' => $accepted['status'],
            'lo_uri' => $accepted['lo_uri'],
            'event_type' => $accepted['event_type'],
            'resource_type' => $accepted['resource_type'],
            'revision' => $accepted['revision'],
            'delivery_id' => $accepted['delivery_id'] ?? null,
        ];
    }

    private function isValidSecret(Request $request): bool
    {
        $providedSecret = $request->headers->get(self::WEBHOOK_SECRET_HEADER);
        if (!hash_equals($this->getParameter('rdc_webhook_secret'), $providedSecret)) {
            $this->logger->warning('RDC webhook request with invalid secret');
            return false;
        }
        return true;
    }

    private function hashEvent(array $metadata): string
    {
        return hash('sha256', json_encode([
            'loUri' => $metadata['loUri'] ?? null,
            'loRevision' => $metadata['loRevision'] ?? null,
            'notificationType' => $metadata['notificationType'] ?? null,
            'triggerType' => $metadata['triggerType'] ?? null,
            'triggerDate' => $metadata['triggerDate'] ?? null,
        ]));
    }

    private function isDuplicateEvent(string $eventHash): bool
    {
        $cacheKey = sprintf('%s%s', self::CACHE_KEY_PREFIX, $eventHash);
        if ($this->redis->exists($cacheKey)) {
            return true;
        }
        $this->redis->setex($cacheKey, self::IDEMPOTENCY_TTL, (string) time());
        return false;
    }

    private function parsePayload(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('RDC webhook payload is not valid JSON', ['error' => json_last_error_msg()]);
            return null;
        }
        if (!is_array($payload) || empty($payload[0])) {
            $this->logger->warning('RDC webhook payload is empty or invalid');
            return null;
        }
        return $payload;
    }

    private function isInvalidMetadata(?array $lo, array $metadata): bool
    {
        return is_null($lo) || is_null($metadata['loUri']) || is_null($metadata['notificationType']);
    }

    private function parseDto(?string $resourceType, array $lo): ?object
    {
        if (is_null($resourceType)) {
            return null;
        }
        return match (strtolower($resourceType)) {
            'service' => RdcApiService::parse($lo),
            'servicerequest' => RdcApiServiceRequest::parse($lo),
            'activity' => RdcApiActivity::parse($lo),
            default => null,
        };
    }

    private function acceptedResponseArray(string $loUri, string $eventType, string $resourceType, ?int $loRevision, ?int $deliveryId = null): array
    {
        $response = [
            'status' => 'accepted',
            'lo_uri' => $loUri,
            'event_type' => $eventType,
            'resource_type' => $resourceType,
            'revision' => $loRevision,
        ];
        if (!is_null($deliveryId)) {
            $response['delivery_id'] = $deliveryId;
        }
        return $response;
    }

    private function isValidBOLMember(Request $request): bool
    {
        $members = array_keys($this->getParameter('rdc_connections'));
        $providedMember = $request->headers->get(self::WEBHOOK_MEMBER_HEADER);
        return in_array($providedMember, $members, true);
    }

}
