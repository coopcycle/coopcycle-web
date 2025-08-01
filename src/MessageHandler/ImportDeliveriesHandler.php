<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Tour;
use AppBundle\Entity\TourRepository;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\RemotePushNotificationManager;
use AppBundle\Service\LiveUpdates;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
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

        $this->updateQueueStatus($queue, DeliveryImportQueue::STATUS_STARTED);

        $result = $this->spreadsheetParser->parse($tempnam, $message->getOptions());

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

        if ($result->hasErrors()) {
            $this->updateQueueStatus($queue, DeliveryImportQueue::STATUS_FAILED, $result->getNormalizedErrors());
        } else {
            $this->updateQueueStatus($queue, DeliveryImportQueue::STATUS_COMPLETED);
        }

        unlink($tempnam);
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
