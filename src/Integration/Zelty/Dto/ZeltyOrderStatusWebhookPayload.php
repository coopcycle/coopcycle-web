<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyOrderStatusWebhookPayload
{
    public function __construct(
        public readonly string $eventName,
        public readonly int $zeltyOrderId,
        public readonly string $status,
    ) {}
}
