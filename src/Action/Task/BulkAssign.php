<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class BulkAssign extends Base
{
    use AssignTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        TaskManager $taskManager,
        protected UserManagerInterface $userManager,
        protected IriConverterInterface $iriConverter,
        protected EntityManagerInterface $entityManager,
        protected AuthorizationCheckerInterface $authorization
    )
    {
        parent::__construct($tokenStorage, $taskManager);
    }

    public function __invoke(Request $request)
    {
        $payload = [];

        $content = $request->getContent();
        if (!empty($content)) {
            $payload = json_decode($content, true);
        }

        $tasks = $payload["tasks"];

        $tasksResults= [];

        foreach($tasks as $task) {
            $taskObj = $this->iriConverter->getResourceFromIri($task);
            $tasksResults[] = $this->assign($taskObj, $payload, $request);
        }

        $this->entityManager->flush();

        return $tasksResults;
    }
}
