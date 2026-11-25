<?php

declare(strict_types=1);

namespace AppBundle\MessageHandler;

use AppBundle\Entity\ImportQueue;
use AppBundle\Exception\ImportCancelledException;
use AppBundle\Import\ImportHandlerInterface;
use AppBundle\Import\ImportRegistry;
use AppBundle\Message\RunsImport;
use AppBundle\Service\ImportStatusManager;
use AppBundle\Service\LiveUpdatesSuspension;
use AppBundle\Service\RemotePushNotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Imports an uploaded file, whatever its rows hold.
 *
 * Reads it while reporting its progress and watching for a cancellation, then saves
 * what it read in a single transaction. What the rows become is the business of the
 * ImportHandlerInterface the import's type names.
 */
#[AsMessageHandler]
class RunImportHandler
{
    /**
     * Geocoding takes a couple of seconds per row, so this is a progress
     * update, and a chance to stop a cancelled import, every half a minute or so
     */
    const PROGRESS_EVERY_ROWS = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImportRegistry $registry,
        private ImportStatusManager $statusManager,
        private LiveUpdatesSuspension $liveUpdatesSuspension,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        // To get a working entity manager back once a failed flush has closed it
        private ManagerRegistry $doctrine,
        )
    {
    }

    public function __invoke(RunsImport $message)
    {
        RemotePushNotificationManager::disable();

        $queue = $this->findQueue($message);

        if (null === $queue) {
            $this->logger->error(sprintf('Could not find job for filename %s', $message->getFilename()));
            return;
        }

        $importHandler = $this->registry->handlerFor($queue->getType());

        $context = ['import_id' => $queue->getId(), 'import_type' => $queue->getType()];

        // The transport hands a message out again when it has not been acknowledged
        // after an hour, which a large file easily takes: without this, a second
        // worker would import the same file alongside the first one.
        // A message retried after an error was put back to pending, see ImportFailureSubscriber.
        if (ImportQueue::STATUS_PENDING !== $queue->getStatus()) {
            $this->logger->info(
                sprintf('Not importing %s, the import is %s', $message->getFilename(), $queue->getStatus()),
                $context
            );
            return;
        }

        // The readers want a file on disk, the imports live on a filesystem that may not be one
        $tempnam = tempnam(sys_get_temp_dir(), 'coopcycle_import');

        if (false === file_put_contents($tempnam, $importHandler->getFilesystem()->read($message->getFilename()))) {
            $this->logger->error('Could not write temp file', $context);
            unlink($tempnam);
            return;
        }

        $this->statusManager->started($queue);

        try {
            $result = $importHandler->getParser()->parse($tempnam, array_merge(
                $importHandler->defaultOptions($queue),
                // What the dispatcher chose when uploading the file
                $queue->getOptions(),
                $message->getOptions(),
                // Picked on the mapping screen. An import without one, from the API or
                // sent before the mapping screen existed, is read by its header names.
                is_null($queue->getColumnMapping()) ? [] : ['column_mapping' => $queue->getColumnMapping()],
                [
                    'on_row' => fn (int $processedRows, int $totalRows, int $errorRows)
                        => $this->onRow($queue, $processedRows, $totalRows, $errorRows),
                ]
            ));

            // It is only checked every few rows while reading the file: check once more,
            // in the same statement that makes the import impossible to cancel from now on
            if (!$this->statusManager->saving($queue)) {
                throw new ImportCancelledException(sprintf('The import of %s was cancelled', $queue->getFilename()));
            }
        } catch (ImportCancelledException $e) {
            $this->logger->info(
                sprintf('Stopped importing %s after %d rows, the import was cancelled', $message->getFilename(), $queue->getProcessedRows()),
                $context
            );
            // Already written by the admin, but this worker may have reported its progress
            // with the status it had in memory since: tell the admin the last word
            $this->statusManager->cancelled($queue);
            unlink($tempnam);
            return;
        }

        $importHandler->beforeSave($queue);

        // All the rows are saved in a single transaction, so that a failure (a deadlock, say)
        // doesn't leave the file half imported. The orders only move from "cart" to "new" once
        // the whole message is handled (see OnDemandHandler): those of a partial import stayed
        // in "cart", and could not be cancelled.
        // It only starts now: what was written while reading the file, the progress above all,
        // has to be seen by the admin as it goes.
        $this->entityManager->beginTransaction();

        // The row being saved, for the error to point at when saving fails
        $rowNumber = null;

        try {
            // A live update per task and per tour is a dozen calls to Centrifugo per line,
            // while the transaction is open: the board reloads once, when it's saved
            // Not an arrow function, which would get a copy of $rowNumber
            $dates = $this->liveUpdatesSuspension->during(function () use ($importHandler, $queue, $result, &$rowNumber) {
                return $importHandler->save($queue, $result, $rowNumber);
            });

            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Importing %s failed on row %s, nothing was imported: %s', $message->getFilename(), $rowNumber ?? '?', $e->getMessage()),
                $context
            );

            $this->rollBack($importHandler, $message->getFilename(), $rowNumber ?? array_key_first($result->getData()));

            $importHandler->onSaveFailed($queue);

            unlink($tempnam);

            // Nothing was imported, and a retry would most likely fail the same way:
            // the file can be imported again as a whole
            throw new UnrecoverableMessageHandlingException(
                sprintf('Importing %s failed: %s', $message->getFilename(), $e->getMessage()), 0, $e
            );
        }

        $importHandler->afterSave($queue);

        if (!empty($dates)) {
            $this->statusManager->saved($queue, $dates);
        }

        // Rows can still fail validation once read, count them too
        if (!is_null($queue->getTotalRows())) {
            $queue->setProgress($queue->getProcessedRows(), $queue->getTotalRows(), count($result->getErrors()));
        }

        if ($result->hasErrors()) {
            $this->statusManager->failed($queue, $result->getNormalizedErrors());
        } else {
            $this->statusManager->completed($queue);
        }

        unlink($tempnam);
    }

    /**
     * By id, which the messages dispatched before it was carried don't have: those are
     * found by filename, as they were then.
     */
    private function findQueue(RunsImport $message): ?ImportQueue
    {
        $repository = $this->entityManager->getRepository(ImportQueue::class);

        $queueId = $message->getQueueId();

        if (!is_null($queueId)) {
            return $repository->find($queueId);
        }

        return $repository->findOneBy(['filename' => $message->getFilename()]);
    }

    private function rollBack(ImportHandlerInterface $importHandler, string $filename, ?int $rowNumber): void
    {
        $connection = $this->entityManager->getConnection();
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        // A failed flush closes the entity manager. Otherwise, it still holds the entities rolled back.
        if ($this->entityManager->isOpen()) {
            $this->entityManager->clear();
        } else {
            $this->doctrine->resetManager();
        }

        /** @var ?ImportQueue $queue */
        $queue = $this->doctrine->getManager()
            ->getRepository(ImportQueue::class)
            ->findOneBy(['filename' => $filename]);

        if (is_null($queue)) {
            return;
        }

        $errors = is_null($rowNumber) ? [] : [[
            'row' => $rowNumber,
            'errors' => [$this->translator->trans($importHandler->errorKey(ImportHandlerInterface::ERROR_ROLLED_BACK))],
        ]];

        $this->statusManager->failed($queue, $errors);
    }

    private function onRow(ImportQueue $queue, int $processedRows, int $totalRows, int $errorRows): void
    {
        $isFirstOrLastRow = 1 === $processedRows || $totalRows === $processedRows;

        if (!$isFirstOrLastRow && 0 !== $processedRows % self::PROGRESS_EVERY_ROWS) {
            return;
        }

        // Before reporting, as the report carries the status this worker has in memory,
        // which would show a cancelled import as running again in the admin
        $this->stopIfCancelled($queue);

        $this->statusManager->progress($queue, $processedRows, $totalRows, $errorRows);
    }

    /**
     * @throws ImportCancelledException
     */
    private function stopIfCancelled(ImportQueue $queue): void
    {
        if ($this->statusManager->isCancelled($queue)) {
            throw new ImportCancelledException(sprintf('The import of %s was cancelled', $queue->getFilename()));
        }
    }
}
