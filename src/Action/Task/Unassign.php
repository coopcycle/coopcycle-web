<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
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
