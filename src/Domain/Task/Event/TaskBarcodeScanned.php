<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;

class TaskBarcodeScanned extends Event implements DomainEvent, HasIconInterface
{

    public function __construct(Task $task)
    {
        parent::__construct($task);
    }

    public function toPayload(): array
    {
        return [];
    }

    public static function messageName(): string
    {
        return 'task:scanned';
    }

    public static function iconName()
    {
        return 'barcode';
    }
}
