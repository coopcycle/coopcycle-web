<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Integration\Zelty\Dto\ZeltyCatalog;
use AppBundle\Integration\Zelty\ZeltyCatalogProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/zelty/webhook/catalog/{restaurantId}',
            input: ZeltyCatalog::class,
            output: false,
            processor: ZeltyCatalogProcessor::class,
        )
    ]
)]
class ZeltyCatalogWebhook {}
