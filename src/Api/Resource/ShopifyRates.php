<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Api\State\ShopifyRatesProcessor;
use AppBundle\Api\State\ShopifyRatesProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(
            controller: NotFoundAction::class,
            output: false,
            read: false
        ),
        new Post(
            uriTemplate: '/shopify/rates/{id}',
            status: 200,
            openapiContext: ['summary' => 'Returns real-time shipping rates for Shopify CarrierService (Advanced plan).'],
            normalizationContext: ['groups' => ['shopify_rates_output']],
            provider: ShopifyRatesProvider::class,
            processor: ShopifyRatesProcessor::class,
        ),
    ],
    formats: ['json']
)]
final class ShopifyRates
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    public array $rate = [];

    #[Groups(['shopify_rates_output'])]
    public array $rates = [];

    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }
}
