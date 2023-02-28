<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait AssignTrait
{
    protected function assign(Task $task, User $user)
    {
        if ($task->isAssigned()) {
            if ($task->isAssignedTo($this->getUser())) {

                return $task; // Do nothing
            }

            if (!$this->getUser()->hasRole('ROLE_ADMIN')) {

                throw new BadRequestHttpException(sprintf('Task #%d is already assigned to "%s"',
                    $task->getId(), $this->getUser()->getUsername()));
            }
        }

        if ($user) {
            $task->assignTo($user);
        }

        return $task;
    }
}
