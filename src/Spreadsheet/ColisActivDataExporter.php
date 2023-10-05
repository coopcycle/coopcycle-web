<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Package;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskEvent;
use AppBundle\Entity\Task\Package as TaskPackage;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskRepository;
use AppBundle\Entity\User;
use AppBundle\Entity\Vehicle;
use AppBundle\Exception\NoDataException;
use AppBundle\Utils\GeoUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use League\Csv\Writer as CsvWriter;

final class ColisActivDataExporter implements DataExporterInterface
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

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

        if (count($deliveryIds) === 0) {
            throw new NoDataException();
        }

        // 2. We load all the tasks matching those deliveries

        $qb = $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t');
        $qb = $qb->join(Address::class, 'a', Expr\Join::WITH, 't.address = a.id');
        $qb = $qb->leftJoin(Organization::class, 'o', Expr\Join::WITH, 't.organization = o.id');
        // Add join with delivery, to exclude standalone tasks
        $qb = $qb->join(Delivery::class, 'd', Expr\Join::WITH, 't.delivery = d.id');

        $qb = $qb->leftJoin(TaskEvent::class, 'te', Expr\Join::WITH, 't.id = te.task AND te.name = :task_done_event');

        $qb
            ->select('t.id')
            ->addSelect('t.type')
            ->addSelect('IDENTITY(t.delivery) AS delivery')
            ->addSelect('a.geo')
            ->addSelect('o.name AS orgName')
            ->addSelect('te.createdAt AS completedAt')
            ;

        $qb
            ->andWhere('t.status = :status_done')
            ->andWhere(
                $qb->expr()->in('t.delivery', $deliveryIds)
            )
            ->setParameter('status_done', Task::STATUS_DONE)
            ->setParameter('task_done_event', 'task:done');

        $tasks = $qb->getQuery()->getArrayResult();

        $taskIds = array_map(fn ($task) => $task['id'], $tasks);
        $packageCountByTask = $this->countPackagesByTask($taskIds);

        $deliveries = array();
        foreach ($tasks as $task) {
            $deliveries[$task['delivery']][$task['type']][] = $task;
        }

        $data = [];

        foreach ($deliveries as $id => $delivery) {

        	if (!isset($delivery['PICKUP'])) {
        		continue;
        	}

            $pickup = current($delivery['PICKUP']);

            $pickupCoords = GeoUtils::asGeoCoordinates($pickup['geo']);

            foreach ($delivery['DROPOFF'] as $dropoff) {

            	$coords = GeoUtils::asGeoCoordinates($dropoff['geo']);

            	$data[] = [
	            	'tour_id' => $id,
	            	'carrier_id' => $pickup['orgName'],
	            	'transport_type' => 'bike',
                    'pickup_time' => $pickup['completedAt']->getTimestamp(),
	            	'pickup_latitude' => $pickupCoords->getLatitude(),
	            	'pickup_longitude' => $pickupCoords->getLongitude(),
                    'delivery_time' => $dropoff['completedAt']->getTimestamp(),
	            	'delivery_latitude' => $coords->getLatitude(),
	            	'delivery_longitude' => $coords->getLongitude(),
                    'package_count' => $packageCountByTask[$dropoff['id']] ?? '',
	            ];
            }
        }

        $csv = CsvWriter::createFromString('');
        $csv->insertOne(array_keys($data[0]));

        $csv->insertAll($data);

        $content = $csv->getContent();

        return $content;
    }

    public function getContentType(): string
    {
        return 'text/csv';
    }

    public function getFilename(\DateTime $start, \DateTime $end): string
    {
        return sprintf('deliveries-colisactiv-%s-%s.csv', $start->format('Y-m-d'), $end->format('Y-m-d'));
    }

    private function countPackagesByTask(array $taskIds)
    {
        $packagesQb = $this->entityManager
            ->getRepository(Package::class)
            ->createQueryBuilder('p');

        $packagesQb = $packagesQb->join(TaskPackage::class, 'tp', Expr\Join::WITH, 'tp.package = p.id');
        $packagesQb = $packagesQb->join(Task::class, 't', Expr\Join::WITH, 'tp.task = t.id');

        $packagesQb
            ->select('t.id AS task')
            ->addSelect('SUM(tp.quantity) AS count')
            ->andWhere(
                $packagesQb->expr()->in('t.id', $taskIds)
            )
            ->groupBy('t.id');

        $packages = $packagesQb->getQuery()->getArrayResult();

        $countByTask = [];
        foreach ($packages as $package) {
            $countByTask[$package['task']] = $package['count'];
        }

        return $countByTask;
    }
}

