<?php

declare(strict_types=1);

namespace Tests\AppBundle\Integration\Rdc;

use AppBundle\Entity\Store;
use AppBundle\Entity\StoreRepository;
use AppBundle\Integration\Rdc\RdcStoreResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RdcStoreResolverTest extends TestCase
{
    public function testResolvesTheStoreLinkedToTheConnection(): void
    {
        $store = new Store();

        $this->assertSame($store, $this->resolver($store)->resolveStore('BOL.MEMBER.NOWASTEPHARMA'));
    }

    public function testDoesNotFallBackOnAnotherStore(): void
    {
        // Another requestor's store would get the deliveries
        $this->assertNull($this->resolver(null)->resolveStore('BOL.MEMBER.NOWASTEPHARMA'));
    }

    private function resolver(?Store $linkedStore): RdcStoreResolver
    {
        $repository = $this->createMock(StoreRepository::class);
        $repository->method('findOneByRdcConnectionId')->with('BOL.MEMBER.NOWASTEPHARMA')->willReturn($linkedStore);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Store::class)->willReturn($repository);

        return new RdcStoreResolver($entityManager, new NullLogger());
    }
}
