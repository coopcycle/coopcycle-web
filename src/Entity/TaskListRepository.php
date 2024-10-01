<?php

namespace AppBundle\Entity;

use AppBundle\Api\Dto\MyTask;
use AppBundle\Api\Dto\MyTaskList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, TaskList::class);

    }

    public function findMyTaskListAsDto(UserInterface $user, \DateTime $date): ?MyTaskList
    {
        $taskList = $this->findOneBy([
            'courier' => $user,
            'date' => $date,
        ]);

        if (null === $taskList) {
            return null;
        }

        $taskIds = $taskList->getItems()->map(function (TaskList\Item $item) {
            return $item->getTask()->getId();
        });

        $queryResult = $this->entityManager->createQueryBuilder()
            ->select([
                't.id',
                't.type',
                't.status',
                'o.id AS orderId',
            ])
            ->from(Task::class, 't')
            ->leftJoin('t.delivery', 'd')
            ->leftJoin('d.order', 'o')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->getQuery()
            ->getArrayResult();
//            ->getResult(\AppBundle\Api\Dto\Task::class);

        //TODO; sort by position

        $tasks = array_map(function ($row) {
            $taskDto = new MyTask(
                $row['id'],
                $row['type'],
                $row['status'],
                $row['orderId']
            );
            return $taskDto;
        }, $queryResult);

        $taskListDto = new MyTaskList($taskList->getId(), $tasks);
        return $taskListDto;
    }
}
