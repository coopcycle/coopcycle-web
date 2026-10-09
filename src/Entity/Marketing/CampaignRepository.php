<?php

namespace AppBundle\Entity\Marketing;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class CampaignRepository extends EntityRepository
{
    /**
     * Scheduled campaigns whose time has come.
     */
    public function findDue(?\DateTime $now = null): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :scheduled')
            ->andWhere('c.scheduledAt IS NOT NULL')
            ->andWhere('c.scheduledAt <= :now')
            ->setParameter('scheduled', Campaign::STATUS_SCHEDULED)
            ->setParameter('now', $now ?? new \DateTime())
            ->orderBy('c.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC');
    }
}
