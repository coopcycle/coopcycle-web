<?php

namespace AppBundle\Entity;

use AppBundle\Api\Dto\MyTaskList;
use AppBundle\Api\Dto\MyTaskDto;
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
                'd.id AS deliveryId',
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
            ->andWhere('t.status != :statusCancelled')
            ->setParameter('taskIds', $orderedTaskIds) // using IN might cause problems with large number of tasks
            ->setParameter('statusCancelled', Task::STATUS_CANCELLED)
            ->getQuery()
            ->getResult();

        $tasksByDeliveryId = array_reduce($tasksQueryResult, function ($carry, $row) {
            $deliveryId = $row['deliveryId'] ?? null;

            if (null === $deliveryId) {
                return $carry;
            }

            $task = $row[0];
            $carry[$deliveryId][] = $task;
            return $carry;
        }, []);

        $tasks = array_map(function ($row) use ($tasksByDeliveryId) {
            $task = $row[0];
            $deliveryId = $row['deliveryId'] ?? null;

            $taskPackages = [];
            $weight = null;

            $tasksInTheSameDelivery = $deliveryId ? $tasksByDeliveryId[$deliveryId] : [];

            if ($task->isPickup()) {
                // for a pickup in a delivery, the serialized weight is the sum of the dropoff weight and
                // the packages are the "sum" of the dropoffs packages
                foreach ($tasksInTheSameDelivery as $task) {
                    if ($task->isPickup()) {
                        continue;
                    }

                    $taskPackages = array_merge($taskPackages, $task->getPackages()->toArray());
                    $weight += $task->getWeight();
                }
            } else {
                $taskPackages = $task->getPackages()->toArray();
                $weight = $task->getWeight();
            }

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
                array_map(function (Task\Package $taskPackage) {
                    $package = $taskPackage->getPackage();
                    return new TaskPackageDto(
                        $package->getShortCode(),
                        $package->getName(),
                        $package->getAverageVolumeUnits(),
                        $taskPackage->getQuantity());
                }, $taskPackages),
                $weight,
                $task->getHasIncidents(),
                $row['organizationName'],
                $task->getMetadata()['delivery_position'] ?? null, //FIXME extract from the query
                $row['orderNumber'] ?? null,
                $task->getMetadata()['payment_method'] ?? null, //FIXME extract from the query
                $row['orderTotal'] ?? null,
            );

            return $taskDto;
        }, $tasksQueryResult);

        $tasksById = array_reduce($tasks, function ($carry, $task) {
            $carry[$task->id] = $task;
            return $carry;
        }, []);

        //restore order of tasks
        $orderedTasks = [];
        foreach ($orderedTaskIds as $taskId) {
            // skip tasks that are not returned by the query
            // that can happen if a task is cancelled, for example
            if (isset($tasksById[$taskId])) {
                $orderedTasks[] = $tasksById[$taskId];
            }
        }

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
