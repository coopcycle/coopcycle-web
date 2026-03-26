<?php

namespace AppBundle\Message\Task;

class PublishLiveUpdate
{
    public function __construct(
        public readonly int $taskId,
        public readonly string $eventClass,
    ) {}
}
