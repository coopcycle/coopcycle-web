<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Task;

class TaskIncidentReported extends Event implements DomainEvent, HasIconInterface
{

    public function __construct(
        Task $task,
        private string $reason,
        private ?string $notes = null,
        private array $data = [],
        private ?Incident $incident = null
    )
    {
        parent::__construct($task);
    }

    public function toPayload()
    {
        return array_merge([
            'reason' => $this->reason,
            'notes' => $this->notes
        ], $this->data);
    }

    public static function messageName(): string
    {
        return 'task:incident-reported';
    }

    public static function iconName()
    {
        return 'exclamation-circle';
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getIncident(): ?Incident
    {
        return $this->incident;
    }
}
