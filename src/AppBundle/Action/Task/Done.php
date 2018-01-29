<?php

namespace AppBundle\Action\Task;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Service\TaskManager;
use AppBundle\Entity\Task;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Done
{
    protected $tokenStorage;
    protected $taskManager;

    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage, TaskManager $taskManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->taskManager = $taskManager;
    }

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
        $task = $data;

        if (!$task->isAssignedTo($this->getUser())) {
            throw new AccessDeniedHttpException(sprintf('User %s cannot update task', $user->getUsername()));
        }

        $this->taskManager->markAsDone($task);

        return $task;
    }
}
