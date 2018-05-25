<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Failed extends Base
{
    /**
     * @Route(
     *   name="api_task_failed",
     *   path="/tasks/{id}/failed",
     *   defaults={
     *     "_api_resource_class"=Task::class,
     *     "_api_item_operation_name"="task_failed"
     *   }
     * )
     * @Method("PUT")
     */
    public function __invoke(Task $data, Request $request)
    {
        $task = $data;

        $this->accessControl($task);
        try {
            $this->taskManager->markAsFailed($task, $this->getNotes($request));
        } catch (PreviousTaskNotCompletedException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $task;
    }
}
