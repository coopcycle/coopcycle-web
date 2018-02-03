<?php

namespace AppBundle\Action\Task;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Service\TaskManager;
use AppBundle\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Failed
{
    protected $tokenStorage;
    protected $doctrine;
    protected $taskManager;

    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage, DoctrineRegistry $doctrine, TaskManager $taskManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
        $this->taskManager = $taskManager;
    }

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

        if (!$task->isAssignedTo($this->getUser())) {
            throw new AccessDeniedHttpException(sprintf('User %s cannot update task', $user->getUsername()));
        }

        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        $reason = null;
        if (isset($data['reason'])) {
            $reason = $data['reason'];
        }

        $this->taskManager->markAsFailed($task, $reason);

        return $task;
    }
}
