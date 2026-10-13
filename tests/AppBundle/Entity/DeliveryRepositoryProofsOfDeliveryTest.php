<?php

declare(strict_types=1);

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The POD export (POST /api/stores/{id}/pod_export) used to select
 * `DISTINCT d`, which makes Postgres compare every column of the delivery,
 * `metadata` included. That column is json, a type without an equality
 * operator, so the export died with
 * "could not identify an equality operator for type json".
 *
 * The DISTINCT is needed in the first place because the query joins the task
 * images: a delivery with several proofs yields several rows.
 *
 * It runs against a real database: the whole point is the SQL.
 */
class DeliveryRepositoryProofsOfDeliveryTest extends KernelTestCase
{
    private const RANGE_START = '2026-09-21 06:00:00';
    private const RANGE_END   = '2026-09-28 05:59:59';

    private ?EntityManagerInterface $entityManager;
    private DeliveryRepository $repository;
    private Store $store;
    private Store $otherStore;

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

        // Every fixture is rolled back, so the test leaves nothing behind and
        // can run against a database that already holds deliveries.
        $this->entityManager->getConnection()->beginTransaction();

        $this->store = $this->createStore('POD fixtures');
        $this->otherStore = $this->createStore('POD fixtures (other store)');
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

    public function testExportsADeliveryWhoseDropoffHasAProof()
    {
        $delivery = $this->createDelivery(proofsAt: ['2026-09-24 14:00:00']);

        $this->assertEquals([$delivery], $this->findIds());
        $this->assertSame(1, $this->countExportable());
    }

    /**
     * The reported bug. A delivery carrying metadata is what puts a json value
     * in the row the query deduplicates on, and several proofs are what make
     * it deduplicate at all.
     */
    public function testListsADeliveryWithSeveralProofsOnce()
    {
        $delivery = $this->createDelivery(
            proofsAt: ['2026-09-24 14:00:00', '2026-09-24 14:01:00', '2026-09-24 14:02:00'],
            metadata: ['external_reference' => 'RDC-4321']
        );

        $this->assertEquals([$delivery], $this->findIds());
        $this->assertSame(1, $this->countExportable());
    }

    /**
     * Metadata alone was enough to break the export, even without duplicate
     * rows to collapse.
     */
    public function testExportsADeliveryCarryingMetadata()
    {
        $delivery = $this->createDelivery(
            proofsAt: ['2026-09-24 14:00:00'],
            metadata: ['external_reference' => 'RDC-1234']
        );

        $this->assertEquals([$delivery], $this->findIds());
    }

    public function testDropsADeliveryWithoutAnyProof()
    {
        $this->createDelivery(proofsAt: []);

        $this->assertEquals([], $this->findIds());
        $this->assertSame(0, $this->countExportable());
    }

    /**
     * The range is matched against the images' creation date, not against the
     * tasks' time windows.
     */
    public function testDropsADeliveryWhoseProofsAreOutsideTheRange()
    {
        $this->createDelivery(proofsAt: ['2026-09-20 23:00:00']);
        $this->createDelivery(proofsAt: ['2026-09-29 08:00:00']);

        $this->assertEquals([], $this->findIds());
    }

    public function testKeepsTheProofsUploadedOnTheBounds()
    {
        $first = $this->createDelivery(proofsAt: [self::RANGE_START]);
        $last  = $this->createDelivery(proofsAt: [self::RANGE_END]);

        $this->assertEqualsCanonicalizing([$first, $last], $this->findIds());
    }

    /**
     * A proof is a dropoff thing: an image uploaded on the pickup is not one.
     */
    public function testDropsAnImageUploadedOnThePickup()
    {
        $this->createDelivery(proofsAt: ['2026-09-24 14:00:00'], onPickup: true);

        $this->assertEquals([], $this->findIds());
    }

    public function testDropsTheDeliveriesOfAnotherStore()
    {
        $this->createDelivery(proofsAt: ['2026-09-24 14:00:00'], store: $this->otherStore);

        $this->assertEquals([], $this->findIds());
    }

    /**
     * The export lists the most recent delivery first.
     */
    public function testOrdersTheDeliveriesByCreationDateDescending()
    {
        $older = $this->createDelivery(proofsAt: ['2026-09-24 14:00:00'], createdAt: '2026-09-22 09:00:00');
        $newer = $this->createDelivery(proofsAt: ['2026-09-24 15:00:00'], createdAt: '2026-09-23 09:00:00');

        $this->assertEquals([$newer, $older], $this->findIds());
    }

    /**
     * @return int[] the ids the export would archive
     */
    private function findIds(): array
    {
        $deliveries = $this->repository->findDeliveriesWithProofsOfDelivery(
            $this->store,
            new \DateTime(self::RANGE_START),
            new \DateTime(self::RANGE_END)
        );

        return array_map(fn (Delivery $delivery) => $delivery->getId(), $deliveries);
    }

    private function countExportable(): int
    {
        return $this->repository->countDeliveriesWithProofsOfDelivery(
            $this->store,
            new \DateTime(self::RANGE_START),
            new \DateTime(self::RANGE_END)
        );
    }

    /**
     * @param string[] $proofsAt    upload date of one task image each
     * @param array<string,mixed> $metadata
     */
    private function createDelivery(
        array $proofsAt,
        array $metadata = [],
        bool $onPickup = false,
        ?Store $store = null,
        ?string $createdAt = null
    ): int {
        $delivery = new Delivery();
        $delivery->setStore($store ?? $this->store);

        if (!empty($metadata)) {
            $delivery->setMetadata($metadata);
        }

        foreach ($delivery->getTasks() as $task) {
            // The address is NOT NULL, and its coordinates are dereferenced
            // when the distance is computed on persist.
            $address = new Address();
            $address->setStreetAddress('48, Rue de Rivoli, 75004 Paris, France');
            $address->setGeo(new GeoCoordinates(48.855, 2.352));
            $task->setAddress($address);

            // done_after / done_before are NOT NULL, and are deliberately set
            // outside the range: the filter looks at the images, not at these.
            $task->setAfter(new \DateTime('2026-09-10 10:00:00'));
            $task->setBefore(new \DateTime('2026-09-10 11:00:00'));
        }

        $carrier = $onPickup ? $delivery->getPickup() : $delivery->getDropoff();

        foreach ($proofsAt as $i => $uploadedAt) {
            $image = new TaskImage();
            // image_name is NOT NULL.
            $image->setImageName(sprintf('pod-%s-%d.jpg', uniqid(), $i));
            $image->setTask($carrier);
            $carrier->addImage($image);

            $this->entityManager->persist($image);
        }

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        // createdAt is set by Gedmo on persist, so it can only be moved once
        // the rows are there.
        foreach ($carrier->getImages() as $i => $image) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE task_image SET created_at = :created_at WHERE id = :id',
                ['created_at' => $proofsAt[$i], 'id' => $image->getId()]
            );
        }

        if (null !== $createdAt) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE task_collection SET created_at = :created_at WHERE id = :id',
                ['created_at' => $createdAt, 'id' => $delivery->getId()]
            );
        }

        return $delivery->getId();
    }

    private function createStore(string $name): Store
    {
        $store = new Store();
        $store->setName($name);

        $this->entityManager->persist($store);
        $this->entityManager->flush();

        return $store;
    }
}
