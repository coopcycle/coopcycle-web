<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Api\Exception\BadRequestHttpException;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class BulkAssign extends Base
{
    use AssignTrait;

    private $userManager;
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

        if (isset($payload['username'])) {
            $user = $this->userManager->findUserByUsername($payload['username']);

            if (!$user) {

                throw new ItemNotFoundException(sprintf('User "%s" does not exist',
                    $this->getUser()->getUsername()));
            }
        } else {
            $user = $this->getUser();
        }

        if (!isset($payload["tasks"])) {
            throw new BadRequestHttpException('Mandatory parameters are missing');
        }

        $tasks = $payload["tasks"];

        $tasksResults= [];

        foreach($tasks as $task) {
            $taskObj = $this->iriConverter->getItemFromIri($task);
            $tasksResults[] = $this->assign($taskObj, $user);
        }

        $this->entityManager->flush();

        return $tasksResults;
    }
}
