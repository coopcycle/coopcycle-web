<?php

declare(strict_types=1);

namespace AppBundle\Message;

final class RdcPickupStatusUpdateMessage
{
    public function __construct(
        public readonly int $taskId,
        public readonly string $coopcycleStatus,
        public readonly ?\DateTimeImmutable $actionTime = null,
    ) {}
}