<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Api\State\ShopifyWebhookProcessor;
use AppBundle\Api\State\ShopifyWebhookProvider;

#[ApiResource(
    operations: [
        new Get(
            controller: NotFoundAction::class,
            output: false,
            read: false
        ),
        new Post(
            uriTemplate: '/shopify/webhook/{id}',
            status: 200,
            openapiContext: ['summary' => 'Receives a webhook from Shopify (orders/create, orders/cancelled).'],
            // The provider reads the raw body for HMAC verification and populates the object manually.
            // We must skip API Platform's deserialization so the Shopify order's "id" field
            // does not overwrite the shop ID that the provider stores in $webhook->id.
            deserialize: false,
            provider: ShopifyWebhookProvider::class,
            processor: ShopifyWebhookProcessor::class,
        ),
    ],
    formats: ['json']
)]
final class ShopifyWebhook
{
    const EVENT_ORDER_CREATED    = 'orders/create';
    const EVENT_ORDER_CANCELLED  = 'orders/cancelled';

    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    public array $payload = [];

    public ?string $topic = null;

    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }
}
