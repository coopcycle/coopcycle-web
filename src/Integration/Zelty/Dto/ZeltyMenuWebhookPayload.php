<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyMenuWebhookPayload
{
    public function __construct(
        public readonly string $eventName,
        public readonly array $data = [],
    ) {}
}
