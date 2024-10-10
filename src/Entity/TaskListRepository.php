<?php

namespace AppBundle\Entity;

use AppBundle\Api\Dto\MyTaskList;
use AppBundle\Api\Dto\MyTaskDto;
use AppBundle\Api\Dto\TaskMetadataDto;
use AppBundle\Api\Dto\TaskPackageDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskListRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct($registry, TaskList::class);

    }

    public function findMyTaskListAsDto(UserInterface $user, \DateTime $date): ?MyTaskList
    {
        $taskListQueryResult = $this->entityManager->createQueryBuilder()
            ->select([
                'tl',
                // objects are listed below to force them being hydrated / pre-fetched by Doctrine
                // https://www.doctrine-project.org/projects/doctrine-orm/en/3.2/reference/dql-doctrine-query-language.html#result-format
                'item',
                'tour',
                'tourItem',
            ])
            ->from(TaskList::class, 'tl')
            ->leftJoin('tl.items', 'item')
            ->leftJoin('item.tour', 'tour')
            ->leftJoin('tour.items', 'tourItem')
            ->where('tl.courier = :courier')
            ->andWhere('tl.date = :date')
            ->setParameter('courier', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        $taskList = $taskListQueryResult[0] ?? null;

        if (null === $taskList) {
            return null;
        }

        $orderedTaskIds = array_map(function (Task $task) {
            return $task->getId();
        }, $taskList->getTasks());

        $tasksQueryResult = $this->entityManager->createQueryBuilder()
            ->select([
                't',
                'o.number AS orderNumber',
                'o.total AS orderTotal',
                'org.name AS organizationName',
                // objects are listed below to force them being hydrated / pre-fetched by Doctrine
                // https://www.doctrine-project.org/projects/doctrine-orm/en/3.2/reference/dql-doctrine-query-language.html#result-format
                'taskPackage',
                'package',
                'incidents',
            ])
            ->from(Task::class, 't')
            ->leftJoin('t.delivery', 'd')
            ->leftJoin('d.order', 'o')
            ->leftJoin('t.organization', 'org')
            ->leftJoin('t.packages', 'taskPackage')
            ->leftJoin('taskPackage.package', 'package')
            ->leftJoin('t.incidents', 'incidents')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $orderedTaskIds) // using IN might cause problems with large number of tasks
            ->getQuery()
            ->getResult();

        $tasks = array_map(function ($row) {
            $task = $row[0];
            $taskDto = new MyTaskDto(
                $task->getId(),
                $task->getCreatedAt(),
                $task->getUpdatedAt(),
                $task->getType(),
                $task->getStatus(),
                $task->getAddress(),
                $task->getDoneAfter(),
                $task->getDoneBefore(),
                $task->getPrevious()?->getId(),
                $task->getNext()?->getId(),
                $task->getTags(),
                $task->isDoorstep(),
                $task->getComments(),
                $task->getPackages()->map(function (Task\Package $package) {
                    return new TaskPackageDto(
                        $package->getPackage()->getName(),
                        $package->getQuantity());
                })->toArray(),
                $task->getHasIncidents(),
                $row['organizationName'],
                new TaskMetadataDto(
                    $task->getMetadata()['delivery_position'] ?? null, //TODO extract from query
                    $row['orderNumber'],
                    $task->getMetadata()['payment_method'] ?? null, //TODO extract from query
                    $row['orderTotal']
                ),
            );

            return $taskDto;
        }, $tasksQueryResult);

        $tasksById = array_reduce($tasks, function ($carry, $task) {
            $carry[$task->id] = $task;
            return $carry;
        }, []);

        //restore order of tasks
        $orderedTasks = array_map(function ($taskId) use ($tasksById) {
            return $tasksById[$taskId];
        }, $orderedTaskIds);

        $taskListDto = new MyTaskList(
            $taskList->getId(),
            $taskList->getCreatedAt(),
            $taskList->getUpdatedAt(),
            $taskList->getDate(),
            $user->getUsername(),
            $orderedTasks,
            $taskList->getDistance(),
            $taskList->getDuration(),
            $taskList->getPolyline(),
            $taskList->getVehicle(),
            $taskList->getTrailer(),
        );
        return $taskListDto;
    }
}
