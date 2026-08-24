<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyDishWebhookPayload
{
    public function __construct(
        public readonly string $eventName,
        public readonly array $data = [],
    ) {}
}
