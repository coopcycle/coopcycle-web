<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Integration\Zelty\Dto\ZeltyOrderStatusWebhookPayload;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ZeltyOrderStatusWebhookDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ZeltyOrderStatusWebhookPayload
    {
        return new ZeltyOrderStatusWebhookPayload(
            eventName: $data['event_name'] ?? '',
            zeltyOrderId: (int) ($data['data']['id'] ?? 0),
            status: $data['data']['status'] ?? '',
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ZeltyOrderStatusWebhookPayload::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ZeltyOrderStatusWebhookPayload::class => true];
    }
}
