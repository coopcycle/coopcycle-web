<?php

declare(strict_types=1);

namespace AppBundle\Controller\Api;

use AppBundle\Integration\Rdc\DTO\RdcApiActivity;
use AppBundle\Integration\Rdc\DTO\RdcApiService;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
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
    private const WEBHOOK_MEMBER_HEADER = 'X-webhook-Source';
    private const WEBHOOK_SECRET_HEADER = 'X-webhook-Secret';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[Route(path: '/api/v1/webhooks/rdc', name: 'api_v1_webhooks_rdc', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        if (!$this->isValidSecret($request)) {
            return $this->errorResponse('Invalid webhook secret', Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->isValidBOLMember($request)) {
            return $this->errorResponse('Invalid BOL member', Response::HTTP_UNAUTHORIZED);
        }

        $events = $this->parsePayload($request);
        if (empty($events)) {
            return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
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
        $resourceType = strtolower((string) ($metadata['resourceType'] ?? ''));
        $triggerType = $metadata['triggerType'] ?? null;
        $loMember = (string) ($request->headers->get(self::WEBHOOK_MEMBER_HEADER) ?? ($metadata['loMemberIdentifier'] ?? ''));

        $dto = $this->parseDto($resourceType, $lo);
        if (is_null($dto)) {
            return $this->errorResult('Unsupported resource type');
        }
        if ($this->isMissingMetadata($lo, $metadata)) {
            return $this->errorResult('Missing required metadata');
        }
        if ($this->isSkippedTrigger($resourceType, $triggerType)) {
            return $this->buildResponse('skipped', $metadata, $resourceType, $triggerType);
        }

        $this->messageBus->dispatch(new RdcMessage(
            loPayload: $lo,
            loMember: $loMember,
            loUri: (string) $metadata['loUri'],
            loRevision: isset($metadata['loRevision']) ? (int) $metadata['loRevision'] : null,
            notificationMetadata: $metadata,
        ));

        return $this->buildResponse('queued', $metadata, $resourceType, $triggerType);
    }

    private function parseDto(string $resourceType, ?array $lo): ?object
    {
        if (empty($lo)) {
            return null;
        }
        return match ($resourceType) {
            'servicerequest' => RdcApiServiceRequest::parse($lo),
            'service' => RdcApiService::parse($lo),
            'activity' => RdcApiActivity::parse($lo),
            default => null,
        };
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

    private function isMissingMetadata(?array $lo, array $metadata): bool
    {
        return is_null($lo)
            || is_null($metadata['loUri'] ?? null)
            || is_null($metadata['notificationType'] ?? null);
    }

    private function isSkippedTrigger(string $resourceType, ?string $triggerType): bool
    {
        return $resourceType === 'servicerequest' && strtolower((string) $triggerType) !== 'create';
    }

    private function isValidSecret(Request $request): bool
    {
        $providedSecret = $request->headers->get(self::WEBHOOK_SECRET_HEADER);
        $configuredSecret = $this->getParameter('rdc_webhook_secret');

        if (is_null($providedSecret) || is_null($configuredSecret)) {
            $this->logger->warning('RDC webhook request with invalid secret');
            return false;
        }
        if (!hash_equals($configuredSecret, $providedSecret)) {
            $this->logger->warning('RDC webhook request with invalid secret');
            return false;
        }
        return true;
    }

    private function isValidBOLMember(Request $request): bool
    {
        $members = array_keys($this->getParameter('rdc_connections'));
        $providedMember = $request->headers->get(self::WEBHOOK_MEMBER_HEADER);
        return in_array($providedMember, $members, true);
    }

    private function buildResponse(string $status, array $metadata, string $resourceType, ?string $triggerType): array
    {
        $response = [
            'status' => $status,
            'lo_uri' => $metadata['loUri'],
            'event_type' => $metadata['notificationType'],
            'resource_type' => $resourceType,
            'revision' => $metadata['loRevision'] ?? null,
        ];
        if (!empty($triggerType)) {
            $response['trigger_type'] = $triggerType;
        }
        return $response;
    }

    private function errorResult(string $message): array
    {
        return ['status' => 'error', 'error' => $message];
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

}
