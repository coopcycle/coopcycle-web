<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Action\Task\AssignTrait;
use AppBundle\Api\Dto\AssignTasksDto;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AssignTasksProcessor implements ProcessorInterface
{
    use AssignTrait;

    public function __construct(
        protected Security $security,
        protected UserManagerInterface $userManager,
        protected AuthorizationCheckerInterface $authorizationChecker,
        protected RequestStack $requestStack,
        protected EntityManagerInterface $entityManager)
    {}

    /**
     * @param AssignTasksDto $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        foreach ($data->tasks as $task) {
            $tasksResults[] = $this->assign($task, ['username' => $data->username], $this->requestStack->getCurrentRequest());
        }

        $this->entityManager->flush();

        return $tasksResults;
    }
}
