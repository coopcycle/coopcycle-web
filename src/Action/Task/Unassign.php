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
    private $userManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        TaskManager $taskManager,
        UserManagerInterface $userManager)
    {
        parent::__construct($tokenStorage, $taskManager);

        $this->userManager = $userManager;
    }

    public function __invoke($data)
    {
        $task = $data;

        $task->unassign();

        return $task;
    }
}
