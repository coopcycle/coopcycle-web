<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Action\Base;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentImage;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskEvent;
use AppBundle\Entity\TaskImage;
use League\Csv\Writer;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Vich\UploaderBundle\Storage\StorageInterface;
use ZipArchive;

class PODExport extends Base
{
    private const MAX_DATE_RANGE_DAYS = 7;
    private const CSV_HEADERS = ['delivery', 'order_number', 'recipient', 'status', 'comment', 'pods', 'incidents'];

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly Filesystem $taskImagesFilesystem,
        private readonly Filesystem $incidentImagesFilesystem,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly DeliveryRepository $deliveryRepository
    ) { }

    public function __invoke(Request $request): Response
    {
        try {
            $params = $this->parseRequest($request);
            $this->validateRequiredParameters($params);

            [$from, $to] = $this->parseDateRange($params);

            $deliveries = $this->deliveryRepository->findDeliveriesByStore(
                $params->get('store'),
                $from,
                $to
            );

            if (empty($deliveries)) {
                throw new NotFoundHttpException('No deliveries found for the specified criteria');
            }

            $zipPath = $this->buildZip($deliveries);

            return $this->createZipResponse($zipPath);

        } catch (\DateException $e) {
            throw new BadRequestHttpException('Invalid date format. Expected format: Y-m-d or Y-m-d H:i:s');
        } catch (\Exception $e) {
            $this->logger?->error('Failed to generate delivery ZIP', [
                'error' => $e->getMessage(),
                'store' => $params->get('store'),
                'from' => $params->get('from'),
                'to' => $params->get('to')
            ]);

            throw $e;
        }
    }
    /**
     * @param mixed $params
     */
    private function validateRequiredParameters($params): void
    {
        $requiredParams = ['store', 'from', 'to'];
        $missingParams = array_filter(
            $requiredParams,
            fn($param) => empty($params->get($param))
        );

        if (!empty($missingParams)) {
            throw new BadRequestHttpException(
                sprintf('Missing required parameters: %s', implode(', ', $missingParams))
            );
        }
    }
    /**
     * @param mixed $params
     */
    private function parseDateRange($params): array
    {
        $from = new \DateTimeImmutable($params->get('from'));
        $to = new \DateTimeImmutable($params->get('to'));

        if ($from > $to) {
            throw new BadRequestHttpException('Start date must be before or equal to end date');
        }

        $daysDiff = $to->diff($from)->days;
        if ($daysDiff > self::MAX_DATE_RANGE_DAYS) {
            throw new BadRequestHttpException(
                sprintf('Date range cannot exceed %d days', self::MAX_DATE_RANGE_DAYS)
            );
        }

        return [$from, $to];
    }

    private function createZipResponse(string $zipPath): Response
    {
        $filename = sprintf('deliveries_%s.zip', date('Y-m-d_H-i-s'));

        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->deleteFileAfterSend(true);

        return $response;
    }
    /**
     * @param array<int,mixed> $deliveries
     */
    private function buildZip(array $deliveries): string
    {
        $zip = new ZipArchive();
        $zipName = tempnam(sys_get_temp_dir(), 'coopcycle_store_pods');

        if ($zip->open($zipName, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Failed to create ZIP archive');
        }

        try {
            $reportData = $this->processDeliveries($deliveries, $zip);
            $this->addReportsToZip($zip, $reportData);
        } finally {
            $zip->close();
        }

        return $zipName;
    }

    /**
     * @return array<int,array<string,mixed>>
     * @param array<int,Delivery> $deliveries
     */
    private function processDeliveries(array $deliveries, ZipArchive $zip): array
    {
        $reportData = [];

        foreach ($deliveries as $delivery) {
            $order = $delivery->getOrder();

            foreach ($delivery->getTasks() as $task) {
                if ($task->getType() === Task::TYPE_DROPOFF) {
                    $taskImagePaths = $this->addTaskImagesToZip($zip, $delivery, $task);
                    $incidentImagePaths = $this->addIncidentDataToZip($zip, $delivery, $task);

                    $allPodPaths = array_merge($taskImagePaths, $incidentImagePaths);

                    $reportData[] = [
                        'delivery_id' => $delivery->getId(),
                        'delivery_url' => $this->urlGenerator->generate(
                            'dashboard_delivery',
                            ['id' => $delivery->getId()],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                        'order_number' => $order?->getNumber() ?? '',
                        'recipient' => $task->getAddress()->getName(),
                        'status' => $task->getStatus(),
                        'comment' => $this->getTaskComment($task) ?? '',
                        'pod_paths' => $allPodPaths,
                        'incidents' => count($task->getIncidents())
                    ];
                }
            }
        }

        return $reportData;
    }
    /**
     * @return string[]
     */
    private function addTaskImagesToZip(ZipArchive $zip, Delivery $delivery, Task $task): array
    {
        $podPaths = [];

        foreach ($task->getImages() as $image) {
            $path = $this->resolvePath($image);
            if (!$path) {
                continue;
            }

            $zipPath = sprintf(
                '/delivery_%s/task_%s/pod_%s',
                $delivery->getId(),
                $task->getId(),
                basename($path)
            );

            $zip->addFromString($zipPath, $this->taskImagesFilesystem->read($path));
            $podPaths[] = $zipPath;
        }

        return $podPaths;
    }

    /**
     * @return string[]
     */
    private function addIncidentDataToZip(ZipArchive $zip, Delivery $delivery, Task $task): array
    {
        $podPaths = [];

        foreach ($task->getIncidents() as $incident) {
            $incidentFolder = sprintf(
                '/delivery_%s/task_%s/incident_%s',
                $delivery->getId(),
                $task->getId(),
                $incident->getId()
            );

            foreach ($incident->getImages() as $image) {
                $path = $this->resolvePath($image);
                if (!$path) {
                    continue;
                }

                $zipPath = sprintf('%s/incident_image_%s', $incidentFolder, basename($path));
                $zip->addFromString($zipPath, $this->incidentImagesFilesystem->read($path));
                $podPaths[] = $zipPath;
            }

            $this->addIncidentInfoFile($zip, $incidentFolder, $incident);
        }

        return $podPaths;
    }

    private function addIncidentInfoFile(ZipArchive $zip, string $incidentFolder, Incident $incident): void
    {
        $infoContent = sprintf(
            "Incident: %s\nDescription: %s\nReported at: %s",
            $incident->getFailureReasonCode(),
            $incident->getDescription(),
            $incident->getCreatedAt()->format('Y-m-d H:i:s')
        );

        $zip->addFromString(sprintf('%s/infos.txt', $incidentFolder), $infoContent);
    }

    /**
     * @param array<int,array<string,mixed>> $reportData
     */
    private function addReportsToZip(ZipArchive $zip, array $reportData): void
    {
        $csv = Writer::createFromString();
        $csv->insertOne(self::CSV_HEADERS);

        foreach ($reportData as $row) {
            $csv->insertOne([
                $row['delivery_url'],
                $row['order_number'],
                $row['recipient'],
                $row['status'],
                $row['comment'],
                implode("\n", $row['pod_paths']),
                $row['incidents']
            ]);
        }

        $htmlContent = $this->twig->render('delivery/delivery_report.html.twig', [
            'deliveries' => $reportData
        ]);

        $zip->addFromString('report.csv', $csv->toString());
        $zip->addFromString('report.html', $htmlContent);
    }

    private function getTaskComment(Task $task): ?string
    {
        $events = $task->getEvents();

        if ($events->isEmpty()) {
            return null;
        }

        $event = $events
            ->filter(fn(TaskEvent $event): bool => $event->getName() === 'task:done')
            ->last();

        if (!$event) {
            return null;
        }

        $notes = $event->getData('notes');
        return !empty($notes) ? $notes : null;
    }

    private function resolvePath(TaskImage|IncidentImage $image): ?string
    {
        $path = ltrim($this->storage->resolveUri($image, 'file'), '/');

        $filesystem = match (true) {
            $image instanceof TaskImage => $this->taskImagesFilesystem,
            $image instanceof IncidentImage => $this->incidentImagesFilesystem,
            default => null
        };

        return $filesystem?->fileExists($path) ? $path : null;
    }
}
