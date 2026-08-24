<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Integration\Zelty\Dto\ZeltyOptionWebhookPayload;
use AppBundle\Integration\Zelty\ZeltyOptionWebhookProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/zelty/webhook/option.update',
            input: ZeltyOptionWebhookPayload::class,
            output: false,
            processor: ZeltyOptionWebhookProcessor::class,
        ),
        new Post(
            uriTemplate: '/zelty/webhook/option_value.availability_update',
            input: ZeltyOptionWebhookPayload::class,
            output: false,
            processor: ZeltyOptionWebhookProcessor::class,
        ),
    ]
)]
class ZeltyOptionWebhook {}
