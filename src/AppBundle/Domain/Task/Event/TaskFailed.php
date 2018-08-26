<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;

class TaskFailed extends Event implements DomainEvent
{
    private $notes;

    public function __construct(Task $task, $notes)
    {
        parent::__construct($task);

        $this->notes = $notes;
    }

    public function toPayload()
    {
        return [
            'notes' => $this->getNotes(),
        ];
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public static function messageName()
    {
        return 'task:failed';
    }
}
