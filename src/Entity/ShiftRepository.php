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

    /**
     * Returns, for each ISO week starting at $from (a Monday), the total slots
     * and total assignments across all shifts starting that week. Weeks with
     * no shifts are omitted (the caller is expected to fill the gaps).
     *
     * @return array<int, array{week_start: string, total_slots: int, total_assignments: int}>
     */
    public function getWeeklyFillRates(\DateTimeImmutable $from, int $weeks): array
    {
        $to = $from->modify(sprintf('+%d weeks', $weeks));

        // Assignment counts are pre-aggregated per shift in a subquery, rather
        // than joined directly, so that a shift with multiple assignments
        // doesn't get its slots counted once per joined row (fan-out).
        $sql = <<<SQL
            SELECT
                date_trunc('week', s.starts_at)::date AS week_start,
                COALESCE(SUM(s.slots), 0)::integer AS total_slots,
                COALESCE(SUM(a.assignment_count), 0)::integer AS total_assignments
            FROM shift s
            LEFT JOIN (
                SELECT shift_id, COUNT(*) AS assignment_count
                FROM shift_assignment
                GROUP BY shift_id
            ) a ON a.shift_id = s.id
            WHERE s.starts_at >= :from AND s.starts_at < :to
            GROUP BY 1
            ORDER BY 1
        SQL;

        return $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ])->fetchAllAssociative();
    }
}
