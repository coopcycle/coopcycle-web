<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Api\Exception\BadRequestHttpException;
use AppBundle\Service\TaskManager;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Unassign extends Base
{
    public function __construct(
        TokenStorageInterface $tokenStorage,
        TaskManager $taskManager)
    {
        parent::__construct($tokenStorage, $taskManager);
    }

    public function __invoke($data)
    {
        $task = $data;

        $task->unassign();

        return $task;
    }
}
