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
}
