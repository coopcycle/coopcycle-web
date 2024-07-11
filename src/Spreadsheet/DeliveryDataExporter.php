<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Package as TaskPackage;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskRepository;
use AppBundle\Entity\User;
use AppBundle\Utils\PriceFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use League\Csv\Writer as CsvWriter;

final class DeliveryDataExporter implements DataExporterInterface
{
    public function __construct(private EntityManagerInterface $entityManager, private PriceFormatter $priceFormatter)
    {}

    public function export(\DateTime $start, \DateTime $end): string
    {
        // 1. We load all the deliveries whose tasks are matching the date range
        // We need to do this, because some deliveries may have one task inside the range,
        // but another task outside the range

        $qb = $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t');
        // Add join with delivery, to exclude standalone tasks
        $qb = $qb->join(Delivery::class, 'd', Expr\Join::WITH, 't.delivery = d.id');
        $qb = TaskRepository::addRangeClause($qb, $start, $end);
        $qb
            ->select('IDENTITY(t.delivery) AS delivery');

        $tasks = $qb->getQuery()->getArrayResult();

        $deliveryIds = array_map(fn ($t) => $t['delivery'], $tasks);
        $deliveryIds = array_values(array_unique($deliveryIds));

        // 2. We load all the tasks matching those deliveries

        $qb = $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t');
        $qb = $qb->join(Address::class, 'a', Expr\Join::WITH, 't.address = a.id');
        $qb = $qb->leftJoin(Organization::class, 'o', Expr\Join::WITH, 't.organization = o.id');
        // Add join with delivery, to exclude standalone tasks
        $qb = $qb->join(Delivery::class, 'd', Expr\Join::WITH, 't.delivery = d.id');

        $qb = $qb->leftJoin(User::class, 'u', Expr\Join::WITH, 't.assignedTo = u.id');

        $qb
            ->select('t.id')
            ->addSelect('t.type')
            ->addSelect('IDENTITY(t.delivery) AS delivery')
            ->addSelect('t.doneAfter')
            ->addSelect('t.doneBefore')
            ->addSelect('t.weight')
            ->addSelect('a.streetAddress')
            ->addSelect('d.distance')
            ->addSelect('o.name AS orgName')
            ->addSelect('u.username as courier')
            ;

        $qb
            ->andWhere(
                $qb->expr()->in('t.delivery', $deliveryIds)
            );

        $tasks = $qb->getQuery()->getArrayResult();

        $taskIds = array_map(fn ($task) => $task['id'], $tasks);

        $packagesByTask = $this->getPackagesByTask($taskIds);

        $ordersByDelivery = $this->getOrdersByDelivery($deliveryIds);

        $deliveries = array();
        foreach ($tasks as $task) {
            $deliveries[$task['delivery']][$task['type']] = $task;
        }

        $deliveries = array_values($deliveries);

        $deliveries = array_map(function ($delivery) use ($ordersByDelivery, $packagesByTask) {

            $weight = 0;
            foreach ($delivery as $task) {
                $weight += $task['weight'];
            }

            $orderNumber = '';
            $orderTotal = '';
            if (isset($ordersByDelivery[$delivery['PICKUP']['delivery']])) {
                $order = $ordersByDelivery[$delivery['PICKUP']['delivery']];
                $orderNumber = $order['number'];
                $orderTotal = $this->priceFormatter->format($order['total'] ?? 0);
            }

            $packages = '';
            if (isset($packagesByTask[$delivery['DROPOFF']['id']])) {
                $packages = implode(', ', $packagesByTask[$delivery['DROPOFF']['id']]);
            }

            return [
                'organization'    => $delivery['PICKUP']['orgName'],
                'pickup.address'  => $delivery['PICKUP']['streetAddress'],
                'pickup.after'    => $delivery['PICKUP']['doneAfter']->format(\DateTime::ATOM),
                'dropoff.address' => $delivery['DROPOFF']['streetAddress'],
                'dropoff.before'  => $delivery['DROPOFF']['doneBefore']->format(\DateTime::ATOM),
                'weight'          => $weight,
                'distance'        => $delivery['PICKUP']['distance'],
                'order.number'    => $orderNumber,
                'order.total'     => $orderTotal,
                'packages'        => $packages,
                'courier'         => $delivery['PICKUP']['courier'],
            ];

        }, $deliveries);

        $csv = CsvWriter::createFromString('');
        $csv->insertOne(array_keys($deliveries[0]));

        $csv->insertAll($deliveries);

        return $csv->getContent();
    }

    public function getContentType(): string
    {
        return 'text/csv';
    }

    public function getFilename(\DateTime $start, \DateTime $end): string
    {
        return sprintf('deliveries-%s-%s.csv', $start->format('Y-m-d'), $end->format('Y-m-d'));
    }

    private function getPackagesByTask(array $taskIds)
    {
        $packagesQb = $this->entityManager
            ->getRepository(Package::class)
            ->createQueryBuilder('p');

        $packagesQb = $packagesQb->join(TaskPackage::class, 'tp', Expr\Join::WITH, 'tp.package = p.id');
        $packagesQb = $packagesQb->join(Task::class, 't', Expr\Join::WITH, 'tp.task = t.id');

        $packagesQb
            ->select('p.name')
            ->addSelect('tp.quantity')
            ->addSelect('t.id AS task')
            ->andWhere(
                $packagesQb->expr()->in('t.id', $taskIds)
            );

        $packages = $packagesQb->getQuery()->getArrayResult();

        $packagesByTask = [];
        foreach ($packages as $package) {
            $packagesByTask[$package['task']][] = sprintf('%d × %s', $package['quantity'], $package['name']);
        }

        return $packagesByTask;
    }

    private function getOrdersByDelivery(array $deliveryIds)
    {
        $ordersQb = $this->entityManager
            ->getRepository(Order::class)
            ->createQueryBuilder('o');

        $ordersQb
            ->select('o.id')
            ->addSelect('o.number')
            ->addSelect('d.id AS delivery')
            ->addSelect('o.total')
            ->innerJoin(Delivery::class, 'd', Expr\Join::WITH, 'd.order = o.id')
            ->andWhere(
                $ordersQb->expr()->in('d.id', $deliveryIds)
            );

        $orders = $ordersQb->getQuery()->getArrayResult();

        $ordersByDelivery = [];
        foreach ($orders as $order) {
            $ordersByDelivery[$order['delivery']] = $order;
        }

        return $ordersByDelivery;
    }
}
