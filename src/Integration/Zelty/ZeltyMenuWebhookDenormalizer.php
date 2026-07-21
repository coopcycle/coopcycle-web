<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Integration\Zelty\Dto\ZeltyMenuWebhookPayload;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ZeltyMenuWebhookDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ZeltyMenuWebhookPayload
    {
        return new ZeltyMenuWebhookPayload(
            eventName: $data['event_name'] ?? '',
            data: $data['data'] ?? [],
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ZeltyMenuWebhookPayload::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ZeltyMenuWebhookPayload::class => true];
    }
}
