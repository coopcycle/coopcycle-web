<?php

declare(strict_types=1);

namespace AppBundle\Message;

class RdcWebhookMessage
{
    public function __construct(
        private readonly string $loUri,
        private readonly string $eventType,
        private readonly array $payload,
        private readonly \DateTimeImmutable $receivedAt,
    ) {
    }

    public function getLoUri(): string
    {
        return $this->loUri;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
