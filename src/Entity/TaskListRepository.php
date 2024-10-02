<?php

namespace AppBundle\Entity;

use AppBundle\Api\Dto\Metadata;
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
                't.createdAt',
                't.updatedAt',
                't.type',
                't.status',
//                'a', //TODO
                't.doneAfter',
                't.doneBefore',
//                't.previous', //TODO
//                't.next', //TODO
                't.comments',
                't.metadata',
                'o.total AS orderTotal',
                'org.name AS orgName',
            ])
            ->from(Task::class, 't')
            ->leftJoin('t.address', 'a')
            ->leftJoin('t.delivery', 'd')
            ->leftJoin('d.order', 'o')
            ->leftJoin('t.organization', 'org')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->getQuery()
            ->getArrayResult();
//            ->getResult(\AppBundle\Api\Dto\Task::class);

        //TODO; sort by position

        $tasks = array_map(function ($row) {
            $taskDto = new MyTask(
                $row['id'],
                $row['createdAt'],
                $row['updatedAt'],
                $row['type'],
                $row['status'],
                null, //TODO: $row['address'],
                $row['doneAfter'],
                $row['doneBefore'],
                null, //TODO: $row['previous'],
                null, //TODO: $row['next'],
                $row['comments'],
                false, //TODO
                $row['orgName'],
                new Metadata(
                    $row['metadata']['delivery_position'],
                    $row['metadata']['order_number'],
                    $row['metadata']['payment_method'],
                    $row['orderTotal']
                ),
            );

            return $taskDto;
        }, $queryResult);

        $taskListDto = new MyTaskList(
            $taskList->getId(),
            $taskList->getCreatedAt(),
            $taskList->getUpdatedAt(),
            $taskList->getDate(),
            $user->getUsername(),
            $tasks,
            $taskList->getDistance(),
            $taskList->getDuration(),
            $taskList->getPolyline(),
            $taskList->getVehicle(),
            $taskList->getTrailer(),
        );
        return $taskListDto;
    }
}
