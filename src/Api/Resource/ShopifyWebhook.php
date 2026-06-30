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
        // Legacy per-shop endpoint (kept for backwards compat with manually registered webhooks)
        new Post(
            uriTemplate: '/shopify/webhook/{id}',
            status: 200,
            openapiContext: ['summary' => 'Receives a Shopify webhook identified by shop DB id.'],
            provider: ShopifyWebhookProvider::class,
            processor: ShopifyWebhookProcessor::class,
        ),
        // Managed-webhook endpoint: shop identified from X-Shopify-Shop-Domain header
        new Post(
            uriTemplate: '/shopify/webhook',
            status: 200,
            openapiContext: ['summary' => 'Receives a Shopify managed webhook (shop identified from header).'],
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
