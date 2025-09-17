<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class MyTasksProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskListRepository $taskListRepository)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        $date = $uriVariables['date'];

        $taskListDto = $this->taskListRepository->findMyTaskListAsDto($user, $date);

        if (null === $taskListDto) {

            $taskList = new TaskList();
            $taskList->setCourier($user);
            $taskList->setDate($date);

            try {
                $this->entityManager->persist($taskList);
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                // If 2 requests are received at the very same time,
                // we can have a race condition
                // @see https://github.com/coopcycle/coopcycle-app/issues/1265
            }

            $taskListDto = $this->taskListRepository->findMyTaskListAsDto($user, $date);
        }

        return $taskListDto;
    }
}
