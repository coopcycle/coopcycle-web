<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class SchedulePublicationRepository extends EntityRepository
{
    public function findOneByWeekStart(\DateTimeInterface $weekStart): ?SchedulePublication
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.weekStart = :weekStart')
            ->setParameter('weekStart', $weekStart->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns the published week starts (Y-m-d strings) in the given range.
     *
     * @return string[]
     */
    public function findWeekStartsBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.weekStart')
            ->andWhere('p.weekStart >= :from')
            ->andWhere('p.weekStart <= :to')
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getArrayResult();

        return array_map(fn (array $row) => $row['weekStart']->format('Y-m-d'), $rows);
    }
}
