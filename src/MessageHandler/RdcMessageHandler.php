<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestProcessor;
use AppBundle\Integration\Rdc\DTO\RdcApiServiceRequest;
use AppBundle\Integration\Rdc\RdcStoreResolver;
use AppBundle\Message\RdcMessage;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RdcMessageHandler
{
    private const IDEMPOTENCY_TTL = 21600;
    private const CACHE_KEY_PREFIX = 'rdc_webhook_event:';

    public function __construct(
        private readonly RdcServiceRequestProcessor $processor,
        private readonly RdcStoreResolver $storeResolver,
        private readonly Redis $redis,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(RdcMessage $message): void
    {
        $eventHash = self::hashMetadata($message->notificationMetadata);

        $key = sprintf('%s%s', self::CACHE_KEY_PREFIX, $eventHash);
        $claimed = $this->redis->set($key, '1', ['nx', 'ex' => self::IDEMPOTENCY_TTL]);
        if (!$claimed) {
            $this->logger->info('RDC webhook event already processed', [
                'event_hash' => $eventHash,
                'lo_uri' => $message->loUri,
            ]);
            return;
        }

        $dto = RdcApiServiceRequest::parse($message->loPayload);

        $store = $this->storeResolver->resolveStore($message->loMember);
        if (is_null($store)) {
            $this->logger->error('Store not found for RDC servicerequest', [
                'event_hash' => $eventHash,
                'lo_uri' => $message->loUri,
                'lo_member' => $message->loMember,
            ]);
            return;
        }

        $delivery = $this->processor->process($dto, $store, $message->loUri, $message->loRevision);

        $this->logger->info('RDC delivery processed', [
            'event_hash' => $eventHash,
            'lo_uri' => $message->loUri,
            'delivery_id' => $delivery->getId(),
        ]);
    }

    private static function hashMetadata(array $metadata): string
    {
        return hash('sha256', json_encode([
            'loUri' => $metadata['loUri'] ?? null,
            'loRevision' => $metadata['loRevision'] ?? null,
            'notificationType' => $metadata['notificationType'] ?? null,
            'triggerType' => $metadata['triggerType'] ?? null,
            'triggerDate' => $metadata['triggerDate'] ?? null,
        ]));
    }
}
