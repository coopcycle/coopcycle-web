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

    public function isWeekPublished(\DateTimeInterface $date): bool
    {
        $weekStart = \DateTimeImmutable::createFromInterface($date)->modify('monday this week');

        return null !== $this->findOneByWeekStart($weekStart);
    }

    /**
     * Keeps only the shifts whose week (the week they start in) is published.
     * Draft weeks must stay invisible to couriers.
     *
     * @param Shift[] $shifts
     * @return Shift[]
     */
    public function filterPublished(array $shifts): array
    {
        if (0 === count($shifts)) {
            return [];
        }

        $weekStartOf = fn (Shift $shift) => \DateTimeImmutable::createFromInterface($shift->getStartsAt())
            ->modify('monday this week')
            ->format('Y-m-d');

        $weekStarts = array_map($weekStartOf, $shifts);
        sort($weekStarts);

        $publishedWeeks = $this->findWeekStartsBetween(
            new \DateTimeImmutable($weekStarts[0]),
            new \DateTimeImmutable(end($weekStarts))
        );

        return array_values(array_filter($shifts,
            fn (Shift $shift) => in_array($weekStartOf($shift), $publishedWeeks, true)));
    }
}
