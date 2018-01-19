<?php

namespace AppBundle\Action\Task;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Task;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Done
{
    use ActionTrait;

    /**
     * @Route(
     *   name="api_task_done",
     *   path="/tasks/{id}/done",
     *   defaults={
     *     "_api_resource_class"=Task::class,
     *     "_api_item_operation_name"="task_done"
     *   }
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $user = $this->getUser();

        $task = $data;

        if (!$task->isAssignedTo($user)) {
            throw new AccessDeniedHttpException(sprintf('User %s cannot update task', $user->getUsername()));
        }

        $task->setStatus(Task::STATUS_DONE);

        return $task;
    }
}
