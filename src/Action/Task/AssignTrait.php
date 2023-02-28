<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait AssignTrait
{
    protected $userManager;

    protected function assign(Task $task, $payload)
    {
        if (isset($payload['username'])) {
            $user = $this->userManager->findUserByUsername($payload['username']);

            if (!$user) {

                throw new ItemNotFoundException(sprintf('User "%s" does not exist',
                    $this->getUser()->getUsername()));
            }
        } else {
            $user = $this->getUser();
        }

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
