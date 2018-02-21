<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class Done extends Base
{
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
    public function __invoke(Task $data, Request $request)
    {
        $task = $data;

        $this->accessControl($task);
        $this->taskManager->markAsDone($task, $this->getNotes($request));

        return $task;
    }
}
