<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Api\Exception\BadRequestHttpException;
use AppBundle\Service\TaskManager;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Assign extends Base
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

    public function __invoke($data, Request $request)
    {
        $task = $data;

        $payload = [];

        $content = $request->getContent();
        if (!empty($content)) {
            $payload = json_decode($content, true);
        }

        if (isset($payload['username'])) {
            $user = $this->userManager->findUserByUsername($payload['username']);

            if (!$user) {

                throw new ItemNotFoundException(sprintf('User "%s" does not exist',
                    $this->getUser()->getUsername()));
            }
        } else {
            $user = $this->getUser();
        }

        if ($task->isAssigned()) {
            if ($task->isAssignedTo($this->getUser())) {

                return $task; // Do nothing
            }

            if (!$this->getUser()->hasRole('ROLE_ADMIN')) {

                throw new BadRequestHttpException(sprintf('Task #%d is already assigned to "%s"',
                    $task->getId(), $this->getUser()->getUsername()));
            }
        }

        if ($user) {
            $task->assignTo($user);
        }

        return $task;
    }
}
