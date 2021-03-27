<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Task;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class TaskListener
{
    public function prePersist(Task $task, LifecycleEventArgs $args)
    {
        if (null === $task->getDoneAfter()) {
            $doneAfter = clone $task->getDoneBefore();
            $doneAfter->modify('-15 minutes');
            $task->setDoneAfter($doneAfter);
        }
    }
}
