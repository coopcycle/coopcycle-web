<?php

namespace AppBundle\Service;

use AppBundle\Entity\ImportQueue;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Writes the status of an import, and tells the dispatch board about it so that
 * the admin reflects it without a reload.
 *
 * mapping (waiting for a dispatcher to map its columns) -> pending -> started (reading
 * the file) -> saving (saving what it read) -> completed or failed.
 *
 * An import also ends as failed when the worker gives up on the message, or the
 * sweep closes it because no worker is handling it any more. It ends as
 * cancelled when a dispatcher stops it before it has saved anything, or when the sweep
 * closes one left waiting for its columns to be mapped.
 *
 * Once it is over, a dispatcher can cancel what it created, once:
 * cancelling -> cancelled, the cancelling itself done by a worker.
 */
class ImportStatusManager
{
    public function __construct(
        // Resolved on each call rather than injected: a failed import is
        // written after the entity manager that failed it has been reset
        private readonly ManagerRegistry $doctrine,
        private readonly LiveUpdates $liveUpdates
    )
    {
    }

    public function started(ImportQueue $queue): void
    {
        $queue->setStatus(ImportQueue::STATUS_STARTED);
        $queue->setStartedAt(new \DateTime());

        $this->publish($queue);
    }

    /**
     * Puts an import that failed with a retry to come back in line, as the
     * handler leaves alone the imports that are not pending.
     */
    public function requeued(ImportQueue $queue): void
    {
        $queue->setStatus(ImportQueue::STATUS_PENDING);

        $this->publish($queue);
    }

    /**
     * Called while the file is being read, so this is written with a plain
     * update rather than a flush, which would also write whatever else the
     * unit of work holds halfway through the file.
     */
    public function progress(ImportQueue $queue, int $processedRows, int $totalRows, int $errorRows): void
    {
        $queue->setProgress($processedRows, $totalRows, $errorRows);

        $this->doctrine->getConnection()->update(
            'import_queue',
            [
                'processed_rows' => $queue->getProcessedRows(),
                'total_rows' => $queue->getTotalRows(),
                'error_rows' => $queue->getErrorRows(),
                'progressed_at' => $queue->getProgressedAt(),
            ],
            ['id' => $queue->getId()],
            ['progressed_at' => Types::DATETIME_MUTABLE]
        );

        $this->liveUpdates->toDispatchers('import:updated', $this->toPayload($queue));
    }

    /**
     * Reads the status from the database, as it is changed by another process
     * (the admin) than the worker importing the file.
     */
    public function isCancelled(ImportQueue $queue): bool
    {
        return ImportQueue::STATUS_CANCELLED === $this->currentStatus($queue);
    }

    public function currentStatus(ImportQueue $queue): string
    {
        return $this->doctrine->getConnection()->fetchOne(
            'SELECT status FROM import_queue WHERE id = ?',
            [$queue->getId()]
        );
    }

    /**
     * Called by a dispatcher. A compare-and-set, as the worker may be moving the
     * import to "saving" at the same time: one of the two wins, never both.
     *
     * @return bool false when the import had gone too far to be cancelled
     */
    public function cancel(ImportQueue $queue): bool
    {
        $finishedAt = new \DateTime();

        $updated = $this->doctrine->getConnection()->executeStatement(
            'UPDATE import_queue SET status = :cancelled, finished_at = :finished_at WHERE id = :id AND status IN (:cancellable)',
            [
                'cancelled' => ImportQueue::STATUS_CANCELLED,
                'finished_at' => $finishedAt,
                'id' => $queue->getId(),
                'cancellable' => ImportQueue::CANCELLABLE_STATUSES,
            ],
            [
                'finished_at' => Types::DATETIME_MUTABLE,
                'cancellable' => ArrayParameterType::STRING,
            ]
        );

        if (1 !== $updated) {
            return false;
        }

        $queue->setStatus(ImportQueue::STATUS_CANCELLED);
        $queue->setFinishedAt($finishedAt);

        $this->liveUpdates->toDispatchers('import:updated', $this->toPayload($queue));

        return true;
    }

    /**
     * Called when a dispatcher has said which field each column of the file holds: the
     * import is now one a worker can pick up.
     *
     * A compare-and-set, so that submitting the mapping screen twice, with a double click
     * or with the back button, hands the file to a worker once and once only.
     *
     * @param array<int,string> $columnMapping
     * @return bool false when the import was no longer waiting for a mapping
     */
    public function queued(ImportQueue $queue, array $columnMapping): bool
    {
        $updated = $this->doctrine->getConnection()->executeStatement(
            'UPDATE import_queue SET status = :pending, column_mapping = :column_mapping WHERE id = :id AND status = :mapping',
            [
                'pending' => ImportQueue::STATUS_PENDING,
                'column_mapping' => $columnMapping,
                'id' => $queue->getId(),
                'mapping' => ImportQueue::STATUS_MAPPING,
            ],
            ['column_mapping' => Types::JSON]
        );

        if (1 !== $updated) {
            return false;
        }

        $queue->setStatus(ImportQueue::STATUS_PENDING);
        $queue->setColumnMapping($columnMapping);

        $this->liveUpdates->toDispatchers('import:updated', $this->toPayload($queue));

        return true;
    }

    /**
     * Called by the worker once the file is read, before it saves the first row:
     * from then on the import can't be cancelled. The counterpart of cancel().
     *
     * @return bool false when the import was cancelled in the meantime
     */
    public function saving(ImportQueue $queue): bool
    {
        $progressedAt = new \DateTime();

        $updated = $this->doctrine->getConnection()->executeStatement(
            'UPDATE import_queue SET status = :saving, progressed_at = :progressed_at WHERE id = :id AND status = :started',
            [
                'saving' => ImportQueue::STATUS_SAVING,
                'progressed_at' => $progressedAt,
                'id' => $queue->getId(),
                'started' => ImportQueue::STATUS_STARTED,
            ],
            ['progressed_at' => Types::DATETIME_MUTABLE]
        );

        if (1 !== $updated) {
            return false;
        }

        $queue->setStatus(ImportQueue::STATUS_SAVING);

        $this->liveUpdates->toDispatchers('import:updated', $this->toPayload($queue));

        return true;
    }

    /**
     * Called by the worker once it has stopped on a cancelled import. The status
     * is already in the database, this is for the admin: the worker may have
     * reported its progress, with the status it had in memory, since the cancel.
     */
    public function cancelled(ImportQueue $queue): void
    {
        $queue->setStatus(ImportQueue::STATUS_CANCELLED);
        $queue->setFinishedAt(new \DateTime());

        $this->publish($queue);
    }

    /**
     * Sent once what the file held is committed: the tasks and tours were saved
     * without a live update each, the boards showing one of these days reload them.
     *
     * @param string[] $dates the days the tasks are on, as Y-m-d
     */
    public function saved(ImportQueue $queue, array $dates): void
    {
        $this->liveUpdates->toDispatchers('import:saved', [
            'type' => $queue->getType(),
            'filename' => $queue->getFilename(),
            'dates' => $dates,
        ]);
    }

    public function completed(ImportQueue $queue): void
    {
        $queue->setStatus(ImportQueue::STATUS_COMPLETED);
        $queue->setFinishedAt(new \DateTime());

        $this->publish($queue);
    }

    /**
     * @param array $errors as stored on the queue, i.e. a list of
     *                      ['row' => int, 'errors' => string[]]
     */
    public function failed(ImportQueue $queue, array $errors = []): void
    {
        $queue->setStatus(ImportQueue::STATUS_FAILED);
        $queue->setFinishedAt(new \DateTime());

        if (!empty($errors)) {
            $queue->setErrors($errors);
        }

        $this->publish($queue);
    }

    /**
     * Called by a dispatcher. A compare-and-set, so that two of them asking at the same time
     * don't send a worker twice.
     *
     * @return bool false when the import isn't over, or what it created was already cancelled
     */
    public function cancellingItems(ImportQueue $queue): bool
    {
        $updated = $this->doctrine->getConnection()->executeStatement(
            'UPDATE import_queue SET items_cancellation = :cancelling WHERE id = :id AND status IN (:over) AND items_cancellation IS NULL',
            [
                'cancelling' => ImportQueue::ITEMS_CANCELLING,
                'id' => $queue->getId(),
                'over' => [ImportQueue::STATUS_COMPLETED, ImportQueue::STATUS_FAILED],
            ],
            ['over' => ArrayParameterType::STRING]
        );

        if (1 !== $updated) {
            return false;
        }

        $queue->setItemsCancellation(ImportQueue::ITEMS_CANCELLING, null, 0, 0);

        $this->liveUpdates->toDispatchers('import:updated', $this->toPayload($queue));

        return true;
    }

    public function itemsCancelProgress(ImportQueue $queue, int $toCancel, int $cancelled, int $skipped): void
    {
        $this->writeItemsCancellation($queue, ImportQueue::ITEMS_CANCELLING, $toCancel, $cancelled, $skipped);
    }

    public function itemsCancelled(ImportQueue $queue, int $toCancel, int $cancelled, int $skipped): void
    {
        $this->writeItemsCancellation($queue, ImportQueue::ITEMS_CANCELLED, $toCancel, $cancelled, $skipped);
    }

    /**
     * The worker failed. Put back to "cancelling" for the retry to go through,
     * or, once it's given up on, to nothing for a dispatcher to ask again.
     */
    public function itemsCancellationFailed(ImportQueue $queue, bool $willRetry): void
    {
        $this->writeItemsCancellation(
            $queue,
            $willRetry ? ImportQueue::ITEMS_CANCELLING : null,
            $queue->getItemsToCancel(),
            $queue->getCancelledItems(),
            $queue->getSkippedItems()
        );
    }

    /**
     * A plain update rather than a flush, which would also write whatever else
     * the worker's unit of work holds
     */
    private function writeItemsCancellation(ImportQueue $queue, ?string $cancellation, ?int $toCancel, int $cancelled, int $skipped): void
    {
        $queue->setItemsCancellation($cancellation, $toCancel, $cancelled, $skipped);

        $this->doctrine->getConnection()->update(
            'import_queue',
            [
                'items_cancellation' => $cancellation,
                'items_to_cancel' => $toCancel,
                'cancelled_items' => $cancelled,
                'skipped_items' => $skipped,
            ],
            ['id' => $queue->getId()]
        );

        $this->liveUpdates->toDispatchers('import:updated', $this->toPayload($queue));
    }

    /**
     * Wraps a message that applies to the import as a whole, rather than to
     * one of its rows, in the shape the admin reads errors in.
     */
    public static function errorForWholeImport(string $message): array
    {
        return [['row' => 0, 'errors' => [$message]]];
    }

    private function publish(ImportQueue $queue): void
    {
        $this->doctrine->getManager()->flush();

        $this->liveUpdates->toDispatchers('import:updated', $this->toPayload($queue));
    }

    private function toPayload(ImportQueue $queue): array
    {
        return [
            'type' => $queue->getType(),
            'filename' => $queue->getFilename(),
            'status' => $queue->getStatus(),
            'startedAt' => $queue->getStartedAt()?->format(\DateTime::ATOM),
            'totalRows' => $queue->getTotalRows(),
            'processedRows' => $queue->getProcessedRows(),
            'errorRows' => $queue->getErrorRows(),
            'itemsCancellation' => $queue->getItemsCancellation(),
            'itemsToCancel' => $queue->getItemsToCancel(),
            'cancelledItems' => $queue->getCancelledItems(),
            'skippedItems' => $queue->getSkippedItems(),
        ];
    }
}
