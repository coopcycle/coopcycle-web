<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;

class TaskListener
{
    private $eventBus;
    private $logger;

    public function __construct(
        MessageBus $eventBus,
        LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->logger = $logger;
    }

    public function prePersist(Task $task, LifecycleEventArgs $args)
    {
        if (null === $task->getDoneAfter()) {
            $doneAfter = clone $task->getDoneBefore();
            $doneAfter->modify('-15 minutes');
            $task->setDoneAfter($doneAfter);
        }
    }

    public function postPersist(Task $task, LifecycleEventArgs $args)
    {
        $this->eventBus->handle(new TaskCreated($task));
    }
}
