<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskAssignment;
use AppBundle\Event\TaskDoneEvent;
use AppBundle\Event\TaskAssignEvent;
use Doctrine\ORM\Query\Expr;
use FOS\UserBundle\Model\UserInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaskManager
{
    private $doctrine;
    private $dispatcher;

    public function __construct(ManagerRegistry $doctrine, EventDispatcherInterface $dispatcher)
    {
        $this->doctrine = $doctrine;
        $this->dispatcher = $dispatcher;
    }

    public function assign(Task $task, UserInterface $user, $position)
    {
        $task->assignTo($user, $position);

        $this->dispatcher->dispatch(TaskAssignEvent::NAME, new TaskAssignEvent($task, $user));
    }

    public function unassign(Task $task)
    {
        $this->doctrine
            ->getManagerForClass(TaskAssignment::class)
            ->remove($task->getAssignment());

        $task->unassign();

        if (null !== $task->getDelivery()) {
            $task->getDelivery()->setStatus(Delivery::STATUS_WAITING);
        }
    }

    public function markAsDone(Task $task)
    {
        $task->setStatus(Task::STATUS_DONE);

        $this->dispatcher->dispatch(TaskDoneEvent::NAME, new TaskDoneEvent($task));
    }
}
