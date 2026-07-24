<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;

class HolidayRequestRepository extends EntityRepository
{
    /**
     * Returns the holiday requests overlapping the given date range.
     *
     * @return HolidayRequest[]
     */
    public function findOverlappingRange(\DateTimeInterface $start, \DateTimeInterface $end, array $statuses = []): array
    {
        $qb = $this->createQueryBuilder('h')
            ->andWhere('h.startDate <= :end')
            ->andWhere('h.endDate >= :start')
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'))
            ->orderBy('h.startDate', 'ASC');

        if (count($statuses) > 0) {
            $qb
                ->andWhere('h.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return HolidayRequest[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.user = :user')
            ->setParameter('user', $user)
            ->orderBy('h.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasApprovedHolidayOnDate(User $user, \DateTimeInterface $date): bool
    {
        $count = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.user = :user')
            ->andWhere('h.status = :status')
            ->andWhere('h.startDate <= :date')
            ->andWhere('h.endDate >= :date')
            ->setParameter('user', $user)
            ->setParameter('status', HolidayRequest::STATUS_APPROVED)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
