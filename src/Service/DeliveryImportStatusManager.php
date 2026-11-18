<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Writes the status of a delivery import, and tells the dispatch board about
 * it so that the admin reflects it without a reload.
 *
 * pending -> started (reading the file) -> saving (saving the deliveries) -> completed or failed.
 *
 * An import also ends as failed when the worker gives up on the message, or the
 * sweep closes it because no worker is handling it any more. It ends as
 * cancelled when a dispatcher stops it while it is still pending or started.
 */
class DeliveryImportStatusManager
{
    public function __construct(
        // Resolved on each call rather than injected: a failed import is
        // written after the entity manager that failed it has been reset
        private readonly ManagerRegistry $doctrine,
        private readonly LiveUpdates $liveUpdates
    )
    {
    }

    public function started(DeliveryImportQueue $queue): void
    {
        $queue->setStatus(DeliveryImportQueue::STATUS_STARTED);
        $queue->setStartedAt(new \DateTime());

        $this->publish($queue);
    }

    /**
     * Puts an import that failed with a retry to come back in line, as the
     * handler leaves alone the imports that are not pending.
     */
    public function requeued(DeliveryImportQueue $queue): void
    {
        $queue->setStatus(DeliveryImportQueue::STATUS_PENDING);

        $this->publish($queue);
    }

    /**
     * Called while the file is being read, so this is written with a plain
     * update rather than a flush, which would also write whatever else the
     * unit of work holds halfway through the file.
     */
    public function progress(DeliveryImportQueue $queue, int $processedRows, int $totalRows, int $errorRows): void
    {
        $queue->setProgress($processedRows, $totalRows, $errorRows);

        $this->doctrine->getConnection()->update(
            'delivery_import_queue',
            [
                'processed_rows' => $queue->getProcessedRows(),
                'total_rows' => $queue->getTotalRows(),
                'error_rows' => $queue->getErrorRows(),
                'progressed_at' => $queue->getProgressedAt(),
            ],
            ['id' => $queue->getId()],
            ['progressed_at' => Types::DATETIME_MUTABLE]
        );

        $this->liveUpdates->toDispatchers('delivery_import:updated', $this->toPayload($queue));
    }

    /**
     * Reads the status from the database, as it is changed by another process
     * (the admin) than the worker importing the file.
     */
    public function isCancelled(DeliveryImportQueue $queue): bool
    {
        return DeliveryImportQueue::STATUS_CANCELLED === $this->currentStatus($queue);
    }

    public function currentStatus(DeliveryImportQueue $queue): string
    {
        return $this->doctrine->getConnection()->fetchOne(
            'SELECT status FROM delivery_import_queue WHERE id = ?',
            [$queue->getId()]
        );
    }

    /**
     * Called by a dispatcher. A compare-and-set, as the worker may be moving the
     * import to "saving" at the same time: one of the two wins, never both.
     *
     * @return bool false when the import was no longer pending or started
     */
    public function cancel(DeliveryImportQueue $queue): bool
    {
        $finishedAt = new \DateTime();

        $updated = $this->doctrine->getConnection()->executeStatement(
            'UPDATE delivery_import_queue SET status = :cancelled, finished_at = :finished_at WHERE id = :id AND status IN (:cancellable)',
            [
                'cancelled' => DeliveryImportQueue::STATUS_CANCELLED,
                'finished_at' => $finishedAt,
                'id' => $queue->getId(),
                'cancellable' => [DeliveryImportQueue::STATUS_PENDING, DeliveryImportQueue::STATUS_STARTED],
            ],
            [
                'finished_at' => Types::DATETIME_MUTABLE,
                'cancellable' => ArrayParameterType::STRING,
            ]
        );

        if (1 !== $updated) {
            return false;
        }

        $queue->setStatus(DeliveryImportQueue::STATUS_CANCELLED);
        $queue->setFinishedAt($finishedAt);

        $this->liveUpdates->toDispatchers('delivery_import:updated', $this->toPayload($queue));

        return true;
    }

    /**
     * Called by the worker once the file is read, before it saves the first delivery:
     * from then on the import can't be cancelled. The counterpart of cancel().
     *
     * @return bool false when the import was cancelled in the meantime
     */
    public function saving(DeliveryImportQueue $queue): bool
    {
        $progressedAt = new \DateTime();

        $updated = $this->doctrine->getConnection()->executeStatement(
            'UPDATE delivery_import_queue SET status = :saving, progressed_at = :progressed_at WHERE id = :id AND status = :started',
            [
                'saving' => DeliveryImportQueue::STATUS_SAVING,
                'progressed_at' => $progressedAt,
                'id' => $queue->getId(),
                'started' => DeliveryImportQueue::STATUS_STARTED,
            ],
            ['progressed_at' => Types::DATETIME_MUTABLE]
        );

        if (1 !== $updated) {
            return false;
        }

        $queue->setStatus(DeliveryImportQueue::STATUS_SAVING);

        $this->liveUpdates->toDispatchers('delivery_import:updated', $this->toPayload($queue));

        return true;
    }

    /**
     * Called by the worker once it has stopped on a cancelled import. The status
     * is already in the database, this is for the admin: the worker may have
     * reported its progress, with the status it had in memory, since the cancel.
     */
    public function cancelled(DeliveryImportQueue $queue): void
    {
        $queue->setStatus(DeliveryImportQueue::STATUS_CANCELLED);
        $queue->setFinishedAt(new \DateTime());

        $this->publish($queue);
    }

    public function completed(DeliveryImportQueue $queue): void
    {
        $queue->setStatus(DeliveryImportQueue::STATUS_COMPLETED);
        $queue->setFinishedAt(new \DateTime());

        $this->publish($queue);
    }

    /**
     * @param array $errors as stored on the queue, i.e. a list of
     *                      ['row' => int, 'errors' => string[]]
     */
    public function failed(DeliveryImportQueue $queue, array $errors = []): void
    {
        $queue->setStatus(DeliveryImportQueue::STATUS_FAILED);
        $queue->setFinishedAt(new \DateTime());

        if (!empty($errors)) {
            $queue->setErrors($errors);
        }

        $this->publish($queue);
    }

    /**
     * Wraps a message that applies to the import as a whole, rather than to
     * one of its rows, in the shape the admin reads errors in.
     */
    public static function errorForWholeImport(string $message): array
    {
        return [['row' => 0, 'errors' => [$message]]];
    }

    private function publish(DeliveryImportQueue $queue): void
    {
        $this->doctrine->getManager()->flush();

        $this->liveUpdates->toDispatchers('delivery_import:updated', $this->toPayload($queue));
    }

    private function toPayload(DeliveryImportQueue $queue): array
    {
        return [
            'filename' => $queue->getFilename(),
            'status' => $queue->getStatus(),
            'startedAt' => $queue->getStartedAt()?->format(\DateTime::ATOM),
            'totalRows' => $queue->getTotalRows(),
            'processedRows' => $queue->getProcessedRows(),
            'errorRows' => $queue->getErrorRows(),
        ];
    }
}
