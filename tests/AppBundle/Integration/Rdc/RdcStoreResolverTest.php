<?php

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Entity\Store;
use AppBundle\Integration\Rdc\RdcStoreResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;

class RdcStoreResolverTest extends TestCase
{
    use ProphecyTrait;

    public function testResolveStoreByRdcConnectionId(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $store = new Store();
        $store->setName('Test Store');

        $repository = $this->prophesize(\AppBundle\Entity\StoreRepository::class);
        $repository->findOneByRdcConnectionId('conn-123')->willReturn($store);

        $entityManager
            ->getRepository(Store::class)
            ->willReturn($repository->reveal());

        $resolver = new RdcStoreResolver($entityManager->reveal(), new NullLogger());

        $result = $resolver->resolveStore('conn-123');

        $this->assertSame($store, $result);
    }

    public function testResolveStoreReturnsNullWhenConnectionIdNotFound(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $repository = $this->prophesize(\AppBundle\Entity\StoreRepository::class);
        $repository->findOneByRdcConnectionId('unknown-conn')->willReturn(null);
        $repository->findStoresWithRdcConnection()->willReturn([]);
        $repository->findSingleStore()->willReturn(null);

        $entityManager
            ->getRepository(Store::class)
            ->willReturn($repository->reveal());

        $resolver = new RdcStoreResolver($entityManager->reveal(), new NullLogger());

        $result = $resolver->resolveStore('unknown-conn');

        $this->assertNull($result);
    }

    public function testResolveStoreFallsBackToSingleStoreWithRdc(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $store = new Store();
        $store->setName('Single RDC Store');

        $repository = $this->prophesize(\AppBundle\Entity\StoreRepository::class);
        $repository->findOneByRdcConnectionId('conn-456')->willReturn(null);
        $repository->findStoresWithRdcConnection()->willReturn([$store]);
        $repository->findSingleStore()->shouldNotBeCalled();

        $entityManager
            ->getRepository(Store::class)
            ->willReturn($repository->reveal());

        $resolver = new RdcStoreResolver($entityManager->reveal(), new NullLogger());

        $result = $resolver->resolveStore('conn-456');

        $this->assertSame($store, $result);
    }

    public function testResolveStoreFallsBackToSingleStoreWhenNoRdcConnectionId(): void
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $store = new Store();
        $store->setName('Single Store');

        $repository = $this->prophesize(\AppBundle\Entity\StoreRepository::class);
        $repository->findStoresWithRdcConnection()->willReturn([]);
        $repository->findSingleStore()->willReturn($store);

        $entityManager
            ->getRepository(Store::class)
            ->willReturn($repository->reveal());

        $resolver = new RdcStoreResolver($entityManager->reveal(), new NullLogger());

        $result = $resolver->resolveStore(null);

        $this->assertSame($store, $result);
    }
}