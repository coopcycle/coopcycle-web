<?php

namespace AppBundle\Action;

use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class MyTasks
{
    use TokenStorageTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
    }

    public function __invoke(Request $request)
    {
        $date = new \DateTime($request->get('date'));

        $taskList = $this->entityManager->getRepository(TaskList::class)->findOneBy([
            'courier' => $this->getUser(),
            'date' => $date,
        ]);

        if ($taskList) {

            $notCancelled = array_filter($taskList->getTasks(), function (Task $task) {
                return !$task->isCancelled();
            });

            $taskList->setTasks($notCancelled);

            return $taskList;
        }

        $taskList = new TaskList();
        $taskList->setCourier($this->getUser());
        $taskList->setDate($date);

        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        return $taskList;
    }
}

