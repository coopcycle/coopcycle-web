<?php

declare(strict_types=1);

namespace AppBundle\Entity\Task;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

class RecurrenceRuleGenerationRepository extends ServiceEntityRepository
{
    /**
     * A run nothing has touched for this long is presumed dead - the worker was
     * killed, the container restarted mid-generation - and stops holding its
     * date. Without it, one crash would lock a date out of generation for good.
     */
    private const STALE_AFTER = '-15 minutes';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurrenceRuleGeneration::class);
    }

    public function findOneByDate(string $date): ?RecurrenceRuleGeneration
    {
        return $this->findOneBy(['date' => new \DateTime($date)]);
    }

    /**
     * Take the date for a new run, unless somebody is already on it.
     *
     * Two dispatchers opening the dashboard at the same moment both read "not
     * running" and both would dispatch, so the decision is made by the database
     * rather than by us. One upsert does it: the row is inserted when the date
     * has never been generated, updated when its last run is over or has gone
     * quiet, and left alone while a run holds it. Re-generating a date is
     * harmless, rules that already produced an order for it are skipped.
     *
     * @return bool Whether this caller is the one that should dispatch.
     */
    public function claim(string $date): bool
    {
        // The timestamps are written by hand rather than with NOW(), because the
        // database runs in UTC while the ORM writes these columns in the
        // application's own timezone - mixing the two makes the ages below lie.
        $now = new \DateTime();

        $claimed = $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO task_rrule_generation
                (date, status, succeeded, failed, attempts, errors, created_at, updated_at)
             VALUES (:date, :status, 0, 0, 0, :errors, :now, :now)
             ON CONFLICT (date) DO UPDATE
             SET status = EXCLUDED.status,
                 succeeded = 0,
                 failed = 0,
                 attempts = 0,
                 errors = EXCLUDED.errors,
                 started_at = NULL,
                 finished_at = NULL,
                 updated_at = EXCLUDED.updated_at
             WHERE task_rrule_generation.status NOT IN (:running)
                OR task_rrule_generation.updated_at < :staleBefore',
            [
                'date' => $date,
                'status' => RecurrenceRuleGeneration::STATUS_PENDING,
                'errors' => '[]',
                'now' => $now,
                'running' => RecurrenceRuleGeneration::runningStatuses(),
                'staleBefore' => new \DateTime(self::STALE_AFTER),
            ],
            [
                'now' => Types::DATETIME_MUTABLE,
                'running' => ArrayParameterType::STRING,
                'staleBefore' => Types::DATETIME_MUTABLE,
            ]
        );

        // Nothing written means the conflicting row was excluded by the WHERE
        // above: a run holds the date, and it is not ours to start.
        return $claimed > 0;
    }
}
