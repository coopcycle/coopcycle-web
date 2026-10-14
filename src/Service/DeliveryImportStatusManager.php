<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Writes the status of a delivery import, and tells the dispatch board about
 * it so that the admin reflects it without a reload.
 *
 * An import is moved out of "started" from three places: the handler when it
 * runs to completion, the worker giving up on the message, and the sweep that
 * closes the ones no worker is handling any more.
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

        $this->liveUpdates->toDispatchers('delivery_import:updated', [
            'filename' => $queue->getFilename(),
            'status' => $queue->getStatus(),
        ]);
    }
}
