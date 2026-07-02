<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class ShiftRepository extends EntityRepository
{
    /**
     * Returns the shifts overlapping the given range, with assignments and users pre-loaded.
     *
     * @return Shift[]
     */
    public function findOverlappingRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.assignments', 'a')
            ->leftJoin('a.user', 'u')
            ->addSelect('a', 'u')
            ->andWhere('s.startsAt < :end')
            ->andWhere('s.endsAt > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Shift[]
     */
    public function findForUserBetween(User $user, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.assignments', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('s.startsAt < :end')
            ->andWhere('s.endsAt > :start')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
