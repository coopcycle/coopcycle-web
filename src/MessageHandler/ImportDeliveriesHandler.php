<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Tour;
use AppBundle\Entity\TourRepository;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\LiveUpdates;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Filesystem $deliveryImportsFilesystem,
        private DeliverySpreadsheetParser $spreadsheetParser,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
        private DeliveryOrderManager $deliveryOrderManager,
        private LiveUpdates $liveUpdates,
        private DeliveryManager $deliveryManager,
        private LoggerInterface $logger,
        private TourRepository $tourRepository,
        private DeliveryCreatedNotifier $deliveryCreatedNotifier,
        private ManagerRegistry $doctrine,
        )
    {
    }

    public function __invoke(ImportDeliveries $message)
    {
        RemotePushNotificationManager::disable();

        $queue = $this->entityManager
            ->getRepository(DeliveryImportQueue::class)
            ->findOneByFilename($message->getFilename());

        if (null === $queue) {
            $this->logger->error(sprintf('Could not find job for filename %s', $message->getFilename()));
            return;
        }

        // The message may be received again after the import was committed,
        // i.e when a handler of the events dispatched after the import fails,
        // or when the worker stops before acknowledging the message.
        // Importing the file again would duplicate all the deliveries.
        if (in_array($queue->getStatus(), [DeliveryImportQueue::STATUS_COMPLETED, DeliveryImportQueue::STATUS_FAILED])) {
            $this->logger->info(sprintf('Import of file %s is already finished, skipping', $message->getFilename()));
            return;
        }

        // Download file locally
        $tempDir = sys_get_temp_dir();
        $tempnam = tempnam($tempDir, 'coopcycle_delivery_import');

        if (false === file_put_contents($tempnam, $this->deliveryImportsFilesystem->read($message->getFilename()))) {
            $this->logger->error('Could not write temp file');
            return;
        }

        $store = $queue->getStore();

        $this->updateQueueStatus($queue, DeliveryImportQueue::STATUS_STARTED);

        $result = $this->spreadsheetParser->parse($tempnam, $message->getOptions());

        // Send a single recap notification once the import is committed,
        // instead of one notification per delivery
        $this->deliveryCreatedNotifier->startBatch();

        // All the rows are imported in a single transaction,
        // so that a failure (i.e a deadlock) doesn't leave the file half imported.
        // Orders only move from "cart" to "new" once the whole message is handled (see OnDemandHandler),
        // so the orders of a partial import would stay in "cart", and could not be cancelled.
        $this->entityManager->beginTransaction();

        $rowNumber = null;

        try {

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

                $store->addDelivery($delivery);
                $this->entityManager->persist($delivery);

                try {
                    $this->deliveryOrderManager->createOrder($delivery, [
                        'throwException' => true
                    ]);
                } catch (NoRuleMatchedException $e) {
                    //FIXME: Shouldn't we create an incident instead ?
                    $errorMessage = $this->translator->trans('delivery.price.error.priceCalculation', [], 'validators');
                    $result->addErrorToRow($rowNumber, $errorMessage);
                }

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
            $this->entityManager->commit();

        } catch (\Throwable $e) {

            $this->logger->error(
                sprintf('Import of file %s failed on row %s: %s', $message->getFilename(), $rowNumber, $e->getMessage()),
                ['store_id' => $store->getId(), 'filename' => $message->getFilename()]
            );

            $this->markAsFailed($message->getFilename(), $rowNumber ?? array_key_first($result->getData()));

            // Drop the notifications for the deliveries that were rolled back
            $this->deliveryCreatedNotifier->abortBatch();

            unlink($tempnam);

            // Nothing was imported, but a retry is likely to fail the same way,
            // and the file can safely be imported again
            throw new UnrecoverableMessageHandlingException(
                sprintf('Import of file %s failed: %s', $message->getFilename(), $e->getMessage()), 0, $e
            );
        }

        $this->deliveryCreatedNotifier->endBatch();

        if ($result->hasErrors()) {
            $this->updateQueueStatus($queue, DeliveryImportQueue::STATUS_FAILED, $result->getNormalizedErrors());
        } else {
            $this->updateQueueStatus($queue, DeliveryImportQueue::STATUS_COMPLETED);
        }

        unlink($tempnam);
    }

    private function markAsFailed(string $filename, ?int $rowNumber): void
    {
        $connection = $this->entityManager->getConnection();
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        // When the exception was thrown while flushing, the entity manager is closed.
        // Otherwise, it still holds the entities that were rolled back.
        if ($this->entityManager->isOpen()) {
            $this->entityManager->clear();
        } else {
            $this->doctrine->resetManager();
        }

        /** @var ?DeliveryImportQueue $queue */
        $queue = $this->entityManager
            ->getRepository(DeliveryImportQueue::class)
            ->findOneByFilename($filename);

        if (is_null($queue)) {
            return;
        }

        $errors = [];
        if (!is_null($rowNumber)) {
            $errors[] = [
                'row' => $rowNumber,
                'errors' => [ $this->translator->trans('delivery.import.error.interrupted', [], 'validators') ],
            ];
        }

        $this->updateQueueStatus($queue, DeliveryImportQueue::STATUS_FAILED, $errors);
    }

    private function updateQueueStatus(DeliveryImportQueue $queue, string $status, array $errors = [])
    {
        $queue->setStatus($status);

        if (DeliveryImportQueue::STATUS_STARTED === $status) {
            $queue->setStartedAt(new \DateTime());
        } else {
            $queue->setFinishedAt(new \DateTime());
        }

        if (!empty($errors)) {
            $queue->setErrors($errors);
        }

        $this->entityManager->flush();

        $this->liveUpdates->toAdmins('delivery_import:updated', [
            'filename' => $queue->getFilename(),
            'status' => $status
        ]);
    }
}
