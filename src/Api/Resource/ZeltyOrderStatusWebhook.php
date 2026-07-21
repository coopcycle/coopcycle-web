<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Integration\Zelty\Dto\ZeltyOrderStatusWebhookPayload;
use AppBundle\Integration\Zelty\ZeltyOrderStatusWebhookProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/zelty/webhook/order.status.update',
            input: ZeltyOrderStatusWebhookPayload::class,
            output: false,
            processor: ZeltyOrderStatusWebhookProcessor::class,
        ),
    ]
)]
class ZeltyOrderStatusWebhook {}
