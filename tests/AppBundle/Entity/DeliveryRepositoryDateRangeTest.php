<?php

declare(strict_types=1);

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Task;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The date filter of the delivery listings (store dashboard & admin) used to
 * filter on d.createdAt, while the listings display & sort t.doneBefore: a
 * delivery created inside the selected range but scheduled after it showed up
 * in the results, which reads as a broken filter.
 *
 * The range is composed with firstPickupOnly() by both listings, so that is
 * what these tests exercise. It runs against a real database: the whole point
 * is the SQL.
 */
class DeliveryRepositoryDateRangeTest extends KernelTestCase
{
    private const RANGE_START = '2026-08-26 00:00:00';
    private const RANGE_END   = '2026-09-25 23:59:59';

    private ?EntityManagerInterface $entityManager;
    private DeliveryRepository $repository;

    /** @var int[] */
    private array $deliveryIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();

        // Persisting a TaskCollection computes its distance/duration through
        // the routing engine (TaskCollectionSubscriber). None of that matters
        // here, and it would tie this test to a running OSRM.
        $routing = $this->createMock(RoutingInterface::class);
        $routing->method('getDistance')->willReturn(0);
        $routing->method('getDuration')->willReturn(0);
        $routing->method('getPolyline')->willReturn('');
        $container->set('routing_service', $routing);

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $this->entityManager->getRepository(Delivery::class);

        // Every fixture is rolled back, so the test leaves no delivery behind
        // and can run against a database that already holds some.
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();

        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        $this->entityManager->close();
        $this->entityManager = null;

        parent::tearDown();
    }

    public function testKeepsADeliveryScheduledInsideTheRange()
    {
        $inside = $this->createDelivery('2026-09-10 11:00:00');

        $this->assertEquals([$inside], $this->filterByRange());
    }

    /**
     * The reported bug: a delivery created while the range was running, but
     * scheduled for a later date, is not part of that range.
     */
    public function testDropsADeliveryScheduledAfterTheRange()
    {
        $this->createDelivery('2026-10-01 11:00:00', createdAt: '2026-09-18 09:00:00');

        $this->assertEquals([], $this->filterByRange());
    }

    public function testDropsADeliveryScheduledBeforeTheRange()
    {
        $this->createDelivery('2026-08-25 23:00:00');

        $this->assertEquals([], $this->filterByRange());
    }

    /**
     * getDeliveryDateRange() widens the bounds to the whole day, so both
     * end days belong to the range.
     */
    public function testKeepsTheDeliveriesOfTheFirstAndLastDay()
    {
        $firstDay = $this->createDelivery('2026-08-26 00:00:00');
        $lastDay  = $this->createDelivery('2026-09-25 23:59:59');

        $this->assertEquals([$firstDay, $lastDay], $this->filterByRange());
    }

    /**
     * A delivery is filtered on the task the listings sort on, the first
     * pickup, no matter how many tasks it holds — and is listed once.
     */
    public function testListsAMultiDropoffDeliveryOnce()
    {
        $multiDropoff = $this->createDelivery('2026-09-10 11:00:00', dropoffs: 3);

        $this->assertEquals([$multiDropoff], $this->filterByRange());
    }

    /**
     * The creation date is not what the filter looks at any more.
     */
    public function testIgnoresTheCreationDate()
    {
        $inside = $this->createDelivery('2026-09-10 11:00:00', createdAt: '2026-01-01 09:00:00');

        $this->assertEquals([$inside], $this->filterByRange());
    }

    /**
     * @return int[] the ids of the fixtures the range keeps, oldest first
     */
    private function filterByRange(): array
    {
        $qb = $this->repository->createQueryBuilderWithTasks();

        $this->repository->firstPickupOnly($qb);
        $this->repository->dateRange(
            $qb,
            new \DateTime(self::RANGE_START),
            new \DateTime(self::RANGE_END)
        );

        $result = $this->scopeToFixtures($qb)
            ->select('d.id')
            ->addOrderBy('t.doneBefore', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (array $row) => $row['id'], $result);
    }

    /**
     * The database is shared with the other tests & the dev fixtures, so the
     * assertions only look at the deliveries this test created.
     */
    private function scopeToFixtures(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->andWhere('d.id IN (:fixtures)')
            ->setParameter('fixtures', $this->deliveryIds);
    }

    private function createDelivery(string $doneBefore, ?string $createdAt = null, int $dropoffs = 1): int
    {
        $delivery = new Delivery();

        for ($i = 1; $i < $dropoffs; $i++) {
            $dropoff = new Task();
            $dropoff->setType(Task::TYPE_DROPOFF);
            $delivery->addTask($dropoff);
        }

        foreach ($delivery->getTasks() as $task) {
            // The address is NOT NULL, and its coordinates are dereferenced
            // when the distance is computed on persist.
            $address = new Address();
            $address->setStreetAddress('48, Rue de Rivoli, 75004 Paris, France');
            $address->setGeo(new GeoCoordinates(48.855, 2.352));
            $task->setAddress($address);

            // done_after / done_before are NOT NULL.
            $task->setAfter(new \DateTime($doneBefore));
            $task->setBefore(new \DateTime($doneBefore));
        }

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        // createdAt is set by Gedmo on persist, so it can only be moved once
        // the row is there.
        if (null !== $createdAt) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE task_collection SET created_at = :created_at WHERE id = :id',
                ['created_at' => $createdAt, 'id' => $delivery->getId()]
            );
        }

        $this->deliveryIds[] = $delivery->getId();

        return $delivery->getId();
    }
}
