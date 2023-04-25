<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Assign extends Base
{
    use AssignTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        TaskManager $taskManager,
        UserManagerInterface $userManager)
    {
        parent::__construct($tokenStorage, $taskManager);

        $this->userManager = $userManager;
    }

    public function __invoke($data, Request $request)
    {
        $task = $data;

        $payload = [];

        $content = $request->getContent();
        if (!empty($content)) {
            $payload = json_decode($content, true);
        }

        return $this->assign($task, $payload);
    }
}
