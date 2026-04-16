<?php

declare(strict_types=1);

namespace AppBundle\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage]
final class RdcDropoffStatusUpdateMessage
{
    public function __construct(
        public readonly int $taskId,
        public readonly string $coopcycleStatus,
        public readonly ?\DateTimeImmutable $actionTime = null,
    ) {}
}