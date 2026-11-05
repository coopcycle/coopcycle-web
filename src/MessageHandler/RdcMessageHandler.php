<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestProcessor;
use AppBundle\Integration\Rdc\Coopcycle\RdcServiceRequestUpdater;
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
        private readonly RdcServiceRequestUpdater $updater,
        private readonly RdcStoreResolver $storeResolver,
        private readonly Redis $redis,
        private readonly LoggerInterface $logger,
        private readonly bool $rdcEnabled = false,
    ) {}

    public function __invoke(RdcMessage $message): void
    {
        if (!$this->rdcEnabled) {
            return;
        }

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

        if ('update' === strtolower($message->notificationMetadata['triggerType'] ?? '')) {
            try {
                $this->updater->update($dto, $message->loUri);
            } catch (\Throwable $e) {
                $this->redis->del($key);
                throw $e;
            }

            return;
        }

        try {
            // A connection without store is misconfigured: fail, so that the
            // message lands in the failure transport and can be replayed once
            // the store is linked.
            $store = $this->storeResolver->resolveStore($message->loMember);
            if (is_null($store)) {
                throw new \RuntimeException(sprintf('No store is linked to the RDC connection "%s"', $message->loMember));
            }

            $delivery = $this->processor->process($dto, $store, $message->loUri, $message->loRevision);
        } catch (\Throwable $e) {
            // Release the claim, otherwise Messenger's retries would be skipped
            // as "already processed" and the event would be lost.
            $this->redis->del($key);
            throw $e;
        }

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
