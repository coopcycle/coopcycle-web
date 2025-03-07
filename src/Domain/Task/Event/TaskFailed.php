<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;

class TaskFailed extends Event implements DomainEvent, HasIconInterface
{
    private $notes;
    private $reason;

    public function __construct(Task $task, $notes = '', $reason = null)
    {
        parent::__construct($task);

        $this->notes = $notes;
        $this->reason = $reason;
    }

    public function toPayload()
    {
        return [
            'notes' => $this->getNotes(),
            'reason' => $this->getReason(),
        ];
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function getReason(): mixed
    {
        return $this->reason;
    }

    public static function messageName(): string
    {
        return 'task:failed';
    }

    public static function iconName()
    {
        return 'warning';
    }
}
