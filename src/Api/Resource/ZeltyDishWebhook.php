<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Integration\Zelty\Dto\ZeltyDishWebhookPayload;
use AppBundle\Integration\Zelty\ZeltyDishWebhookProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/zelty/webhook/dish.update',
            input: ZeltyDishWebhookPayload::class,
            output: false,
            processor: ZeltyDishWebhookProcessor::class,
        ),
        new Post(
            uriTemplate: '/zelty/webhook/dish.delete',
            input: ZeltyDishWebhookPayload::class,
            output: false,
            processor: ZeltyDishWebhookProcessor::class,
        ),
        new Post(
            uriTemplate: '/zelty/webhook/dish.availability_update',
            input: ZeltyDishWebhookPayload::class,
            output: false,
            processor: ZeltyDishWebhookProcessor::class,
        ),
    ]
)]
class ZeltyDishWebhook {}
