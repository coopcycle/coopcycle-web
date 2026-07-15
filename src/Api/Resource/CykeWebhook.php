<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\ApiResource;
use AppBundle\Api\State\CykeWebhookProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/cyke/webhook',
            status: 200,
            openapiContext: ['summary' => 'Receives a webhook from Cyke.'],
            normalizationContext: ['groups' => ['cyke_webhook_output']],
            denormalizationContext: ['groups' => ['cyke_webhook_input']],
            security: 'is_granted(\'PUBLIC_ACCESS\')',
            processor: CykeWebhookProcessor::class
        )
    ]
)]
final class CykeWebhook
{
    // https://docs.cyke.io/webhooks
    const DELIVERY_SAVED     = 'delivery_saved';
    const DELIVERY_READY     = 'delivery_ready';
    const DELIVERY_SCHEDULED = 'delivery_scheduled';
    const DELIVERY_PICKED_UP = 'delivery_picked_up';
    const DELIVERY_DELIVERED = 'delivery_delivered';
    const DELIVERY_FAILED    = 'delivery_failed';
    const DELIVERY_CANCELLED = 'delivery_cancelled';

    #[SerializedName('event_id')]
    #[Groups(['cyke_webhook_input'])]
    public $eventId;

    #[SerializedName('event_type')]
    #[Groups(['cyke_webhook_input'])]
    public $eventType;

    #[Groups(['cyke_webhook_input'])]
    public array $payload = [];

    public static function isValidEvent(string $eventType): bool
    {
        return in_array($eventType, [
            self::DELIVERY_SAVED,
            self::DELIVERY_READY,
            self::DELIVERY_SCHEDULED,
            self::DELIVERY_PICKED_UP,
            self::DELIVERY_DELIVERED,
            self::DELIVERY_FAILED,
            self::DELIVERY_CANCELLED,
        ]);
    }
}
