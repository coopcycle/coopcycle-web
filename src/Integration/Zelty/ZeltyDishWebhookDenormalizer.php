<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Integration\Zelty\Dto\ZeltyDishWebhookPayload;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ZeltyDishWebhookDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ZeltyDishWebhookPayload
    {
        return new ZeltyDishWebhookPayload(
            eventName: $data['event_name'] ?? '',
            data: $data['data'] ?? [],
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ZeltyDishWebhookPayload::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ZeltyDishWebhookPayload::class => true];
    }
}
