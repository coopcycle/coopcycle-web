<?php

declare(strict_types=1);

namespace AppBundle\Import;

use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Tour;
use AppBundle\Entity\TourRepository;
use AppBundle\Service\DeliveryCreatedNotifier;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Spreadsheet\SpreadsheetParseResult;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Saves the deliveries a file holds, with their orders and their tours.
 */
class DeliveryImportHandler implements ImportHandlerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $deliveryImportsFilesystem,
        private readonly DeliverySpreadsheetParser $parser,
        private readonly ValidatorInterface $validator,
        private readonly DeliveryOrderManager $deliveryOrderManager,
        private readonly DeliveryManager $deliveryManager,
        private readonly TourRepository $tourRepository,
        private readonly DeliveryCreatedNotifier $deliveryCreatedNotifier,
    )
    {
    }

    public function getType(): string
    {
        return ImportQueue::TYPE_DELIVERIES;
    }

    public function getParserType(): string
    {
        return 'delivery';
    }

    public function getFilesystem(): Filesystem
    {
        return $this->deliveryImportsFilesystem;
    }

    public function getParser(): AbstractSpreadsheetParser
    {
        return $this->parser;
    }

    public function defaultOptions(ImportQueue $queue): array
    {
        return [];
    }

    /**
     * A single recap notification once the import is committed, rather than one per delivery
     */
    public function beforeSave(ImportQueue $queue): void
    {
        $this->deliveryCreatedNotifier->startBatch();
    }

    public function afterSave(ImportQueue $queue): void
    {
        $this->deliveryCreatedNotifier->endBatch();
    }

    public function onSaveFailed(ImportQueue $queue): void
    {
        // The deliveries they were about are rolled back
        $this->deliveryCreatedNotifier->abortBatch();
    }

    public function errorKey(string $kind): string
    {
        return sprintf('deliveries.imports.error.%s', $kind);
    }

    public function save(ImportQueue $queue, SpreadsheetParseResult $result, ?int &$rowNumber): array
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
}
