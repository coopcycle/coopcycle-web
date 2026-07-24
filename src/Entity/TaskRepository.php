<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskList;
use DateTime;
use AppBundle\Utils\Barcode\Barcode;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;

class TaskRepository extends EntityRepository
{
    public function findUnassignedByDate(\DateTime $date)
    {
        $start = clone $date;
        $end = clone $date;

        return self::addRangeClause($this->createQueryBuilder('t'), $start, $end)
            ->andWhere(sprintf('t.%s IS NULL', 'assignedTo'))
            ->getQuery()
            ->getResult();
    }

    public function findByDate(\DateTime $date)
    {
        $start = clone $date;
        $end = clone $date;

        return $this->findByDateRange($start, $end);
    }

    public function findByDateRange(\DateTime $start, \DateTime $end)
    {
        return self::addRangeClause($this->createQueryBuilder('t'), $start, $end)
            ->getQuery()
            ->getResult();
    }

    public function findByDateRangeOrderByPosition(\DateTime $start, \DateTime $end)
    {
        $qb = self::addRangeClause($this->createQueryBuilder('t'), $start, $end)
            ->leftJoin(TaskCollectionItem::class, 'i', Expr\Join::WITH, 'i.task = t.id')
            ->leftJoin(TaskList::class, 'tl', Expr\Join::WITH, 'i.parent = tl.id')
            ->leftJoin(User::class, 'u', Expr\Join::WITH, 'u.id = t.assignedTo')
            ->addOrderBy('u.username', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ;

        return $qb->getQuery()->getResult();
    }

    public static function addRangeClause(QueryBuilder $qb, \DateTime $start, \DateTime $end): QueryBuilder
    {
        // @see https://github.com/martin-georgiev/postgresql-for-doctrine
        // @see https://www.postgresql.org/docs/9.4/rangetypes.html
        // @see https://www.postgresql.org/docs/9.4/functions-range.html

        return $qb->andWhere('OVERLAPS(TSRANGE(t.doneAfter, t.doneBefore), CAST(:range AS tsrange)) = TRUE')
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')));
    }

    public function findBySubscriptionAndDate(Task\RecurrenceRule $subscription, DateTime $date)
    {
        $start = clone $date;
        $end = clone $date;

        return self::addRangeClause($this->createQueryBuilder('t'), $start, $end)
            ->andWhere('t.recurrenceRule = :subscription')
            ->setParameter('subscription', $subscription)
            ->getQuery()
            ->getResult();
    }

    public function findByBarcode(string $barcode): ?Task
    {
        $barcode = BarcodeUtils::parse($barcode);

        if ($barcode->isInternal() && $barcode->getEntityType() === Barcode::TYPE_TASK) {
            return $this->find($barcode->getEntityId());
        }

        /* TODO: I'm using raw sql here, but doctrine doesn't support json fields and i don't
                 want to install doctrine json functions just for this
            @see https://github.com/ScientaNL/DoctrineJsonFunctions
        */
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT id FROM task WHERE metadata->>'barcode' = :barcode";
        $stmt = $conn->prepare($sql);
        $id = $stmt->executeQuery(['barcode' => $barcode->getRawBarcode()])->fetchOne();
        if ($id) {
            return $this->find($id);
        }
        return null;
    }

    /**
     * Aggregates historical delivery demand (non-cancelled DROPOFF tasks) into
     * (day-of-week, hour) buckets, one count per week in the lookback window.
     *
     * Times are stored in UTC but bucketed in the co-op's local timezone, so the
     * seasonality reflects local wall-clock rush hours.
     *
     * @return array<int, array<int, array<int, int>>>
     *         $samples[$isoDow][$hour][$weeksAgo] = count (zero-filled)
     */
    public function getDropoffDemandSamples(
        \DateTimeImmutable $windowEndLocal,
        int $weeks,
        string $timezone,
        int $openHour,
        int $closeHour
    ): array {
        $startLocal = $windowEndLocal->modify(sprintf('-%d days', $weeks * 7));

        $tz = new \DateTimeZone($timezone);
        $utc = new \DateTimeZone('UTC');

        // The done_before column is UTC, so translate the local window bounds to UTC
        // for an index-friendly range scan
        $startUtc = (new \DateTime($startLocal->format('Y-m-d H:i:s'), $tz))
            ->setTimezone($utc)->format('Y-m-d H:i:s');
        $endUtc = (new \DateTime($windowEndLocal->format('Y-m-d H:i:s'), $tz))
            ->setTimezone($utc)->format('Y-m-d H:i:s');

        $sql = <<<SQL
            SELECT
                FLOOR(EXTRACT(EPOCH FROM (CAST(:end_local AS timestamp) - local_ts)) / 604800)::int AS weeks_ago,
                EXTRACT(ISODOW FROM local_ts)::int AS dow,
                EXTRACT(HOUR FROM local_ts)::int AS hour,
                COUNT(*) AS cnt
            FROM (
                SELECT (t.done_before AT TIME ZONE 'UTC' AT TIME ZONE :tz) AS local_ts
                FROM task t
                WHERE t.type = 'DROPOFF'
                    AND t.status <> 'CANCELLED'
                    AND t.done_before >= :start_utc
                    AND t.done_before < :end_utc
            ) sub
            WHERE EXTRACT(HOUR FROM local_ts) >= :open_hour
                AND EXTRACT(HOUR FROM local_ts) < :close_hour
            GROUP BY weeks_ago, dow, hour
            SQL;

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, [
                'end_local' => $windowEndLocal->format('Y-m-d H:i:s'),
                'tz' => $timezone,
                'start_utc' => $startUtc,
                'end_utc' => $endUtc,
                'open_hour' => $openHour,
                'close_hour' => $closeHour,
            ])
            ->fetchAllAssociative();

        // Zero-fill the full grid so quiet buckets are real zeros (which matter for
        // the mean & variance), not missing data
        $samples = [];
        for ($dow = 1; $dow <= 7; $dow++) {
            for ($hour = $openHour; $hour < $closeHour; $hour++) {
                $samples[$dow][$hour] = array_fill(0, $weeks, 0);
            }
        }

        foreach ($rows as $row) {
            $weeksAgo = (int) $row['weeks_ago'];
            if ($weeksAgo < 0 || $weeksAgo >= $weeks) {
                continue;
            }
            $samples[(int) $row['dow']][(int) $row['hour']][$weeksAgo] = (int) $row['cnt'];
        }

        return $samples;
    }
}
