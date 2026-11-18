<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Tour;
use AppBundle\Entity\TourRepository;
use AppBundle\Exception\DeliveryImportCancelledException;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
class ImportDeliveriesHandler
{
    /**
     * Geocoding takes a couple of seconds per row, so this is a progress
     * update, and a chance to stop a cancelled import, every half a minute or so
     */
    const PROGRESS_EVERY_ROWS = 10;

    const HEARTBEAT_EVERY_DELIVERIES = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Filesystem $deliveryImportsFilesystem,
        private DeliverySpreadsheetParser $spreadsheetParser,
        private ValidatorInterface $validator,
        private DeliveryOrderManager $deliveryOrderManager,
        private DeliveryImportStatusManager $statusManager,
        private DeliveryManager $deliveryManager,
        private LoggerInterface $logger,
        private TourRepository $tourRepository
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
            $result = $this->spreadsheetParser->parse($tempnam, array_merge($message->getOptions(), [
                'on_row' => fn (int $processedRows, int $totalRows, int $errorRows)
                    => $this->onRow($queue, $processedRows, $totalRows, $errorRows),
            ]));

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

        $savedDeliveries = 0;

        foreach ($result->getData() as $rowNumber => $deliveryImportData) {

            // Pricing each delivery takes a while on a large file too:
            // show the import is still alive, so the sweep leaves it alone
            if (0 === ++$savedDeliveries % self::HEARTBEAT_EVERY_DELIVERIES) {
                $this->statusManager->progress($queue, $queue->getProcessedRows(), $queue->getTotalRows(), $queue->getErrorRows());
            }

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
        }

        $this->entityManager->flush();

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
