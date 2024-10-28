<?php

namespace AppBundle\Entity;

use AppBundle\Api\Dto\MyTaskListDto;
use AppBundle\Api\Dto\MyTaskDto;
use AppBundle\Api\Dto\MyTaskMetadataDto;
use AppBundle\Api\Dto\TaskPackageDto;
use AppBundle\Entity\Sylius\Order;
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

    public function findMyTaskListAsDto(UserInterface $user, \DateTime $date): ?MyTaskListDto
    {

        /**
         * IMPORTANT: The queries below are optimized for list operations.
         * So that the number of queries is constant and does not depend on the number of tasks.
         * Be careful when adding/modifying them
         * (check Symfony Profiler/Doctine, the number of "Database Queries"
         * should be equal to the number of "Different statements").
         */

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
                'delivery.id AS deliveryId',
                'o.id AS orderId',
                'o.number AS orderNumber',
                'o.total AS orderTotal',
                'org.name AS organizationName',
                'loopeatDetails.returns AS loopeatReturns',
                // objects are listed below to force them being hydrated / pre-fetched by Doctrine
                // https://www.doctrine-project.org/projects/doctrine-orm/en/3.2/reference/dql-doctrine-query-language.html#result-format
                'taskPackage',
                'package',
            ])
            ->from(Task::class, 't')
            ->leftJoin('t.delivery', 'delivery')
            ->leftJoin('delivery.order', 'o')
            ->leftJoin('t.organization', 'org')
            ->leftJoin('o.loopeatDetails', 'loopeatDetails')
            ->leftJoin('t.packages', 'taskPackage')
            ->leftJoin('taskPackage.package', 'package')
            ->where('t.id IN (:taskIds)')
            ->andWhere('t.status != :statusCancelled')
            ->setParameter('taskIds', $orderedTaskIds) // using IN might cause problems with large number of items
            ->setParameter('statusCancelled', Task::STATUS_CANCELLED)
            ->getQuery()
            ->getResult();


        $tasksByDeliveryId = array_reduce($tasksQueryResult, function ($carry, $row) {
            $deliveryId = $row['deliveryId'] ?? null;

            if (null === $deliveryId) {
                return $carry;
            }

            $task = $row[0];
            $carry[$deliveryId][] = $task; // append to an array
            return $carry;
        }, []);


        $tasksWithIncidentsQueryResult = $this->entityManager->createQueryBuilder()
            ->select([
                't.id AS taskId',
                'COUNT(incidents.id) AS incidentCount',
            ])
            ->from(Task::class, 't')
            ->leftJoin('t.incidents', 'incidents')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $orderedTaskIds) // using IN might cause problems with large number of items
            ->groupBy('t.id')
            ->getQuery()
            ->getResult();

        $tasksWithIncidents = array_reduce($tasksWithIncidentsQueryResult, function ($carry, $row) {
            $carry[$row['taskId']] = $row['incidentCount'];
            return $carry;
        }, []);


        $orderIds = array_reduce($tasksQueryResult, function ($carry, $row) {
            $orderId = $row['orderId'] ?? null;

            if (null === $orderId) {
                return $carry;
            }

            $carry[$orderId] = $orderId; // using an associative array to avoid duplicates
            return $carry;
        }, []);


        $paymentMethodsQueryResult = $this->entityManager->createQueryBuilder()
            ->select([
                'o.id AS orderId',
                'paymentMethod.code AS paymentMethodCode',
            ])
            ->from(Order::class, 'o')
            ->leftJoin('o.payments', 'payment')
            ->leftJoin('payment.method', 'paymentMethod')
            ->where('o.id IN (:orderIds)')
            ->setParameter('orderIds', $orderIds) // using IN might cause problems with large number of items
            ->getQuery()
            ->getResult();

        $paymentMethodsByOrderId = array_reduce($paymentMethodsQueryResult, function ($carry, $row) {
            $carry[$row['orderId']][] = $row['paymentMethodCode']; // append to an array
            return $carry;
        }, []);


        $zeroWasteOrdersQueryResult = $this->entityManager->createQueryBuilder()
            ->select([
                'o.id AS orderId',
                'COUNT(reusablePackaging.id) AS reusablePackagingCount',
            ])
            ->from(Order::class, 'o')
            ->leftJoin('o.items', 'orderItem')
            ->leftJoin('orderItem.variant', 'productVariant')
            ->leftJoin('productVariant.product', 'product')
            ->leftJoin('product.reusablePackagings', 'reusablePackaging')
            ->where('o.id IN (:orderIds)')
            ->andWhere('product.reusablePackagingEnabled = TRUE')
            ->setParameter('orderIds', $orderIds) // using IN might cause problems with large number of items
            ->groupBy('o.id')
            ->getQuery()
            ->getResult();

        $zeroWasteOrders = array_reduce($zeroWasteOrdersQueryResult, function ($carry, $row) {
            $carry[$row['orderId']] = $row['reusablePackagingCount'];
            return $carry;
        }, []);


        $tasks = array_map(function ($row) use ($tasksByDeliveryId, $tasksWithIncidents, $paymentMethodsByOrderId, $zeroWasteOrders) {
            $task = $row[0];
            $deliveryId = $row['deliveryId'] ?? null;
            $orderId = $row['orderId'] ?? null;

            $taskPackages = [];
            $weight = null;

            $tasksInTheSameDelivery = $deliveryId ? $tasksByDeliveryId[$deliveryId] : [];

            if ($task->isPickup()) {
                // for a pickup in a delivery, the serialized weight is the sum of the dropoff weight and
                // the packages are the "sum" of the dropoffs packages
                foreach ($tasksInTheSameDelivery as $t) {
                    if ($t->isPickup()) {
                        continue;
                    }

                    $taskPackages = array_merge($taskPackages, $t->getPackages()->toArray());
                    $weight += $t->getWeight();
                }
            } else {
                $taskPackages = $task->getPackages()->toArray();
                $weight = $task->getWeight();
            }

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
                ($tasksWithIncidents[$task->getId()] ?? 0) > 0,
                $row['organizationName'],
                new MyTaskMetadataDto(
                    $task->getMetadata()['delivery_position'] ?? null, //FIXME extract from the query
                    $row['orderNumber'] ?? null,
                    $this->getPaymentMethod($paymentMethodsByOrderId, $orderId),
                    $row['orderTotal'] ?? null,
                    $this->getLoopeatReturns($orderId, $task, $row),
                    $this->getIsZeroWaste($orderId, $zeroWasteOrders)
                )
            );

            return $taskDto;
        }, $tasksQueryResult);


        $taskDtosById = array_reduce($tasks, function ($carry, $task) {
            $carry[$task->id] = $task;
            return $carry;
        }, []);


        //restore order of tasks
        $orderedTasks = [];
        foreach ($orderedTaskIds as $taskId) {
            // skip tasks that are not returned by the query
            // that can happen if a task is cancelled, for example
            if (isset($taskDtosById[$taskId])) {
                $orderedTasks[] = $taskDtosById[$taskId];
            }
        }


        $taskListDto = new MyTaskListDto(
            $taskList->getId(),
            $taskList->getCreatedAt(),
            $taskList->getUpdatedAt(),
            $taskList->getDate(),
            $user->getUsername(),
            $orderedTasks,
            $taskList->getDistance(),
            $taskList->getDuration(),
            $taskList->getPolyline(),
        );
        return $taskListDto;
    }

    private function getPaymentMethod($paymentMethodsByOrderId, ?int $orderId): ?string
    {
        if (null === $orderId) {
            return null;
        }

        $paymentMethods = $paymentMethodsByOrderId[$orderId] ?? null;

        if (null === $paymentMethods || count($paymentMethods) === 0) {
            return null;
        }

        //FIXME what payment method to show if there are multiple?
        return $paymentMethods[0];
    }

    private function getLoopeatReturns(?int $orderId, $task, $row): ?bool
    {
        if (null === $orderId) {
            return null;
        }

        if (!$task->isDropoff()) {
            return false;
        }

        return $row['loopeatReturns'] && count($row['loopeatReturns']) > 0;
    }

    private function getIsZeroWaste(?int $orderId, $zeroWasteOrders): ?bool
    {
        if (null === $orderId) {
            return null;
        }

        return ($zeroWasteOrders[$orderId] ?? 0) > 0;
    }
}
