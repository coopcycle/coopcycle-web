<?php

namespace AppBundle\Action\Task;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Service\TaskManager;
use AppBundle\Entity\Task;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

abstract class Base
{
    protected $tokenStorage;
    protected $taskManager;

    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage, TaskManager $taskManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->taskManager = $taskManager;
    }

    protected function getNotes(Request $request)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        if (isset($data['notes'])) {
            return $data['notes'];
        }
    }

    protected function accessControl(Task $task)
    {
        if (!$task->isAssignedTo($this->getUser())) {
            throw new AccessDeniedHttpException(sprintf('User %s cannot update task', $this->getUser()->getUsername()));
        }
    }
}
