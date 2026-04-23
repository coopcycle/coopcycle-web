<?php

declare(strict_types=1);

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Store>
 */
class StoreRepository extends EntityRepository
{
    public function findOneByRdcConnectionId(string $rdcConnectionId): ?Store
    {
        return $this->findOneBy(['rdcConnectionId' => $rdcConnectionId]);
    }

    public function findStoresWithRdcConnection(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.rdcConnectionId IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    public function findSingleStore(): ?Store
    {
        $stores = $this->findAll();

        if (count($stores) === 1) {
            return $stores[0];
        }

        return null;
    }
}
