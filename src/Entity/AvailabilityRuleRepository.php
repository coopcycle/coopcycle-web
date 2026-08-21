<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class AvailabilityRuleRepository extends EntityRepository
{
    /**
     * @return AvailabilityRule[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
