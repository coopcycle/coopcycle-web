<?php

namespace AppBundle\Action;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

        $taskList = $this->loadExisting($date);

        if (null === $taskList) {

            $taskList = new TaskList();
            $taskList->setCourier($this->getUser());
            $taskList->setDate($date);

            try {
                $this->entityManager->persist($taskList);
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                // If 2 requests are received at the very same time,
                // we can have a race condition
                // @see https://github.com/coopcycle/coopcycle-app/issues/1265
                $taskList = $this->loadExisting($date);
            }
        }

        return $taskList;
    }

    /**
     * @param \DateTime $date
     * @return TaskList|null
     */
    private function loadExisting(\DateTime $date): ?TaskList
    {
        $taskList = $this->entityManager->getRepository(TaskList::class)
            ->findOneBy([
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

        return null;
    }
}

