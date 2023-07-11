<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class BulkAssign extends Base
{
    use AssignTrait;

    private $iriConverter;
    private $entityManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        TaskManager $taskManager,
        UserManagerInterface $userManager,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager)
    {
        parent::__construct($tokenStorage, $taskManager);

        $this->userManager = $userManager;
        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;
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
            $taskObj = $this->iriConverter->getItemFromIri($task);
            $tasksResults[] = $this->assign($taskObj, $payload);
        }

        $this->entityManager->flush();

        return $tasksResults;
    }
}
