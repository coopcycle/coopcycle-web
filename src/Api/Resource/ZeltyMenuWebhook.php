<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Integration\Zelty\Dto\ZeltyMenuWebhookPayload;
use AppBundle\Integration\Zelty\ZeltyMenuWebhookProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/zelty/webhook/menu.update',
            input: ZeltyMenuWebhookPayload::class,
            output: false,
            processor: ZeltyMenuWebhookProcessor::class,
        ),
        new Post(
            uriTemplate: '/zelty/webhook/menu.delete',
            input: ZeltyMenuWebhookPayload::class,
            output: false,
            processor: ZeltyMenuWebhookProcessor::class,
        ),
        new Post(
            uriTemplate: '/zelty/webhook/menu.availability_update',
            input: ZeltyMenuWebhookPayload::class,
            output: false,
            processor: ZeltyMenuWebhookProcessor::class,
        ),
    ]
)]
class ZeltyMenuWebhook {}
