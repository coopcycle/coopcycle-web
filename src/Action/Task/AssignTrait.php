<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Entity\Task;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait AssignTrait
{
    protected function assign(Task $task, $payload)
    {
        $user = $this->getUser();
        if (isset($payload['username'])) {

            $user = $this->userManager->findUserByUsername($payload['username']);

            if (!$user) {

                throw new ItemNotFoundException(sprintf('User "%s" does not exist',
                    $payload['username']));
            }
        }
        if (!$this->authorization->isGranted('ROLE_DISPATCHER') && $task->isAssigned()) {

            throw new BadRequestHttpException(sprintf('Task #%d is already assigned to "%s"',
                $task->getId(), $task->getAssignedCourier()->getUsername()));
        }

        if ($task->isAssignedTo($user)) {

            return $task; // Do nothing
        }

        if (!is_null($task->getDelivery())) {
            $task->getDelivery()->assignTo($user);
        } else {
            $task->assignTo($user);
        }

        return $task;
    }
}
