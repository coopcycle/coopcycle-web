<?php

declare(strict_types=1);

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Store;
use AppBundle\Service\RoutingInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * `findByLoUri()` is how an RDC message retried after a BOL failure finds the
 * delivery saved by the previous attempt. It is raw SQL, so it runs against a
 * real database.
 */
class DeliveryRepositoryFindByLoUriTest extends KernelTestCase
{
    private const LO_URI = 'https://bol-api.example.com/service-requests/f9b10095';

    private ?EntityManagerInterface $entityManager;
    private DeliveryRepository $repository;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();

        // Persisting a TaskCollection computes its distance/duration through
        // the routing engine, which would tie this test to a running OSRM.
        $routing = $this->createMock(RoutingInterface::class);
        $routing->method('getDistance')->willReturn(0);
        $routing->method('getDuration')->willReturn(0);
        $routing->method('getPolyline')->willReturn('');
        $container->set('routing_service', $routing);

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $this->entityManager->getRepository(Delivery::class);

        $this->entityManager->getConnection()->beginTransaction();

        $this->store = new Store();
        $this->store->setName('RDC fixtures');
        $this->entityManager->persist($this->store);
        $this->entityManager->flush();
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

    public function testFindsDeliveryByLoUriInDeliveryMetadata(): void
    {
        $delivery = $this->createDelivery(function (Delivery $delivery) {
            $delivery->setMetadata('rdc.lo_uri', self::LO_URI);
        });

        $this->assertSame($delivery->getId(), $this->repository->findByLoUri(self::LO_URI)?->getId());
    }

    public function testFindsLegacyDeliveryByLoUriInPickupMetadata(): void
    {
        $delivery = $this->createDelivery(function (Delivery $delivery) {
            $delivery->getPickup()->setMetadata('rdc_lo_uri', self::LO_URI);
        });

        $this->assertSame($delivery->getId(), $this->repository->findByLoUri(self::LO_URI)?->getId());
    }

    public function testReturnsNullForUnknownLoUri(): void
    {
        $this->createDelivery(function (Delivery $delivery) {
            $delivery->setMetadata('rdc.lo_uri', self::LO_URI);
        });

        $this->assertNull($this->repository->findByLoUri(sprintf('%s-other', self::LO_URI)));
    }

    private function createDelivery(callable $configure): Delivery
    {
        $delivery = new Delivery();
        $delivery->setStore($this->store);

        foreach ($delivery->getTasks() as $task) {
            $address = new Address();
            $address->setStreetAddress('Place des Grands Hommes, 33000 Bordeaux, France');
            $address->setGeo(new GeoCoordinates(44.842, -0.576));
            $task->setAddress($address);
            $task->setAfter(new \DateTime('2026-10-21 17:00:00'));
            $task->setBefore(new \DateTime('2026-10-21 17:30:00'));
        }

        $configure($delivery);

        $this->entityManager->persist($delivery);
        $this->entityManager->flush();

        return $delivery;
    }
}
