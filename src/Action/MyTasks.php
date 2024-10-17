<?php

namespace AppBundle\Action;

use AppBundle\Entity\TaskListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\TaskList;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class MyTasks
{
    use TokenStorageTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskListRepository $taskListRepository
    )
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke(Request $request)
    {
        $user = $this->getUser();
        $date = new \DateTime($request->get('date'));

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
