<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Tour;
use AppBundle\Entity\TourRepository;
use AppBundle\Exception\DeliveryImportCancelledException;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryImportStatusManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\LiveUpdatesSuspension;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class ImportDeliveriesHandler
{
    /**
     * Geocoding takes a couple of seconds per row, so this is a progress
     * update, and a chance to stop a cancelled import, every half a minute or so
     */
    const PROGRESS_EVERY_ROWS = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Filesystem $deliveryImportsFilesystem,
        private DeliverySpreadsheetParser $spreadsheetParser,
        private ValidatorInterface $validator,
        private DeliveryOrderManager $deliveryOrderManager,
        private DeliveryImportStatusManager $statusManager,
        private DeliveryManager $deliveryManager,
        private LoggerInterface $logger,
        private TourRepository $tourRepository,
        private DeliveryCreatedNotifier $deliveryCreatedNotifier,
        private TranslatorInterface $translator,
        private LiveUpdatesSuspension $liveUpdatesSuspension,
        // To get a working entity manager back once a failed flush has closed it
        private ManagerRegistry $doctrine,
        )
    {
    }

    public function __invoke(ImportDeliveries $message)
    {
        RemotePushNotificationManager::disable();

        // Download file locally
        $tempDir = sys_get_temp_dir();
        $tempnam = tempnam($tempDir, 'coopcycle_delivery_import');

        if (false === file_put_contents($tempnam, $this->deliveryImportsFilesystem->read($message->getFilename()))) {
            $this->logger->error('Could not write temp file');
            return;
        }

        $queue = $this->entityManager
            ->getRepository(DeliveryImportQueue::class)
            ->findOneByFilename($message->getFilename());

        if (null === $queue) {
            $this->logger->error(sprintf('Could not find job for filename %s', $message->getFilename()));
            unlink($tempnam);
            return;
        }

        $store = $queue->getStore();

        // The transport hands a message out again when it has not been acknowledged
        // after an hour, which a large file easily takes: without this, a second
        // worker would import the same file alongside the first one.
        // A message retried after an error was put back to pending, see DeliveryImportFailureSubscriber.
        if (DeliveryImportQueue::STATUS_PENDING !== $queue->getStatus()) {
            $this->logger->info(
                sprintf('Not importing deliveries from %s, the import is %s', $message->getFilename(), $queue->getStatus()),
                ['store_id' => $store->getId()]
            );
            unlink($tempnam);
            return;
        }

        $this->statusManager->started($queue);

        try {
            $result = $this->spreadsheetParser->parse($tempnam, array_merge(
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
                throw new DeliveryImportCancelledException(sprintf('The import of %s was cancelled', $queue->getFilename()));
            }
        } catch (DeliveryImportCancelledException $e) {
            $this->logger->info(
                sprintf('Stopped importing deliveries from %s after %d rows, the import was cancelled', $message->getFilename(), $queue->getProcessedRows()),
                ['store_id' => $store->getId()]
            );
            // Already written by the admin, but this worker may have reported its progress
            // with the status it had in memory since: tell the admin the last word
            $this->statusManager->cancelled($queue);
            unlink($tempnam);
            return;
        }

        // A single recap notification once the import is committed, rather than one per delivery
        $this->deliveryCreatedNotifier->startBatch();

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
            $dates = $this->liveUpdatesSuspension->during(function () use ($queue, $result, &$rowNumber) {
                return $this->saveDeliveries($queue, $result, $rowNumber);
            });

            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Importing deliveries from %s failed on row %s, nothing was imported: %s', $message->getFilename(), $rowNumber ?? '?', $e->getMessage()),
                ['store_id' => $store->getId()]
            );

            $this->rollBack($message->getFilename(), $rowNumber ?? array_key_first($result->getData()));

            // The deliveries they were about are rolled back
            $this->deliveryCreatedNotifier->abortBatch();

            unlink($tempnam);

            // Nothing was imported, and a retry would most likely fail the same way:
            // the file can be imported again as a whole
            throw new UnrecoverableMessageHandlingException(
                sprintf('Importing deliveries from %s failed: %s', $message->getFilename(), $e->getMessage()), 0, $e
            );
        }

        $this->deliveryCreatedNotifier->endBatch();

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
     * @param ?int $rowNumber set to the row being saved, for the error to point at if it fails
     *
     * @return string[] the days the tasks saved are on, as Y-m-d
     */
    private function saveDeliveries(DeliveryImportQueue $queue, SpreadsheetParseResult $result, ?int &$rowNumber): array
    {
        $store = $queue->getStore();

        $dates = [];

        foreach ($result->getData() as $rowNumber => $deliveryImportData) {

            $delivery = $deliveryImportData['delivery'];

            // Validate data
            $violations = $this->validator->validate($delivery);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    if ($violation->getInvalidValue() instanceof \Stringable) {
                        $errorMessage = sprintf('%s %s: %s', $violation->getPropertyPath(), $violation->getMessage(), (string) $violation->getInvalidValue());
                    } else {
                        $errorMessage = sprintf('%s %s', $violation->getPropertyPath(), $violation->getMessage());
                    }
                    $result->addErrorToRow($rowNumber, $errorMessage);
                }

                continue;
            }

            $this->deliveryManager->setDefaults($delivery);

            // To find the deliveries that came from a file
            $delivery->setMetadata('delivery_import_id', $queue->getId());

            $store->addDelivery($delivery);
            $this->entityManager->persist($delivery);

            foreach ($delivery->getTasks() as $task) {
                foreach ([$task->getAfter(), $task->getBefore()] as $date) {
                    if (!is_null($date)) {
                        $dates[$date->format('Y-m-d')] = true;
                    }
                }
            }

            // When no rule matches, the order gets a placeholder price of 0
            // and an incident for a dispatcher to review it, as with the API.
            // The delivery is imported anyway: without an order, it could never be priced.
            $this->deliveryOrderManager->createOrder($delivery);

            if ($deliveryImportData['tourName']) {
                foreach ($delivery->getTasks() as $task) {
                    $tourName = $deliveryImportData['tourName'];
                    $date = $task->getAfter();
                    $tour = $this->tourRepository->findByNameAndDate($tourName, $date);

                    if (is_null($tour)) {
                        $tour = new Tour();
                        $tour->setName($tourName);
                        $tour->setDate($date);
                        $this->entityManager->persist($tour);
                        $this->entityManager->flush();
                    }

                    $tour->addTask($task);
                }
            }

            // The order is flushed while it's created, not what comes after it: flushed with
            // the next row, a failure would point at that row
            $this->entityManager->flush();
        }

        $dates = array_keys($dates);
        sort($dates);

        return $dates;
    }

    private function rollBack(string $filename, ?int $rowNumber): void
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

        /** @var ?DeliveryImportQueue $queue */
        $queue = $this->doctrine->getManager()
            ->getRepository(DeliveryImportQueue::class)
            ->findOneBy(['filename' => $filename]);

        if (is_null($queue)) {
            return;
        }

        $errors = is_null($rowNumber) ? [] : [[
            'row' => $rowNumber,
            'errors' => [$this->translator->trans('deliveries.imports.error.rolled_back')],
        ]];

        $this->statusManager->failed($queue, $errors);
    }

    private function onRow(DeliveryImportQueue $queue, int $processedRows, int $totalRows, int $errorRows): void
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
     * @throws DeliveryImportCancelledException
     */
    private function stopIfCancelled(DeliveryImportQueue $queue): void
    {
        if ($this->statusManager->isCancelled($queue)) {
            throw new DeliveryImportCancelledException(sprintf('The import of %s was cancelled', $queue->getFilename()));
        }
    }
}
