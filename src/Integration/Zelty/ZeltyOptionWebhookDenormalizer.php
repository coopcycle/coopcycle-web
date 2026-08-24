<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Integration\Zelty\Dto\ZeltyOptionWebhookPayload;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ZeltyOptionWebhookDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ZeltyOptionWebhookPayload
    {
        return new ZeltyOptionWebhookPayload(
            eventName: $data['event_name'] ?? '',
            restaurantId: (int) ($data['restaurant_id'] ?? 0),
            data: $data['data'] ?? [],
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ZeltyOptionWebhookPayload::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ZeltyOptionWebhookPayload::class => true];
    }
}
