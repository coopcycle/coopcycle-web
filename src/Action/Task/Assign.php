<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Assign extends Base
{
    use AssignTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        TaskManager $taskManager,
        protected UserManagerInterface $userManager,
        protected AuthorizationCheckerInterface $authorization
    )
    {
        parent::__construct($tokenStorage, $taskManager);
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
