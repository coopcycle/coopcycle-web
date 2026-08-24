<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyOptionWebhookPayload
{
    public function __construct(
        public readonly string $eventName,
        public readonly int $restaurantId,
        public readonly array $data = [],
    ) {}
}
