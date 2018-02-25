<?php

namespace AppBundle\Event;

use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use Symfony\Component\EventDispatcher\Event;

class TaskCollectionChangeEvent extends Event
{
    const NAME = 'task_collection.change';

    protected $taskCollection;

    public function __construct(TaskCollectionInterface $taskCollection)
    {
        $this->taskCollection = $taskCollection;
    }

    public function getTaskCollection()
    {
        return $this->taskCollection;
    }
}
