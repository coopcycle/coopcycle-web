<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskEvent;
use AppBundle\Event\TaskCreateEvent;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaskListener
{
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(Task $task, LifecycleEventArgs $args)
    {
        $this->dispatcher->dispatch(TaskCreateEvent::NAME, new TaskCreateEvent($task));
    }
}
