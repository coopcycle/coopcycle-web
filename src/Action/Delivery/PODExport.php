<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Action\Base;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Incident\IncidentImage;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskEvent;
use AppBundle\Entity\TaskImage;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Vich\UploaderBundle\Storage\StorageInterface;

class PODExport extends Base
{

    private readonly DeliveryRepository $deliveryRepository;

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly Filesystem $taskImagesFilesystem,
        private readonly Filesystem $incidentImagesFilesystem,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        EntityManagerInterface $entityManager
    ) {
        $this->deliveryRepository = $entityManager->getRepository(Delivery::class);
    }

    public function __invoke(Request $request)
    {
        $params = $this->parseRequest($request);

        if (empty($params->get('store')) || empty($params->get('from')) || empty($params->get('to'))) {
            throw new BadRequestHttpException('Missing parameters');
        }

        $from = new \DateTimeImmutable($params->get('from'));
        $to = new \DateTimeImmutable($params->get('to'));

        // Find all deliveries link to a store from date A to date B
        $e = $this->deliveryRepository->findDeliveriesByStore(
            $params->get('store'),
            $from, $to
        );

        $zip = $this->buildZip($e);

        $response = new Response(
            file_get_contents($zip),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="pod.zip"',
            ]
        );

        unlink($zip);
        return $response;


    }
    /**
     * @param mixed $deliveries
     */
    private function buildZip($deliveries)
    {
        $zip = new \ZipArchive();
        $zipName = tempnam(sys_get_temp_dir(), 'coopcycle_store_pods');
        $zip->open($zipName, \ZipArchive::CREATE);

        // Build data once for both CSV and HTML
        $reportData = $this->setupZip($deliveries, $zip);

        // Generate CSV
        $csv = Writer::createFromString();
        $csv->insertOne(['delivery', 'order_number', 'recipient', 'status', 'comment', 'pods', 'incidents']);

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

        // Generate HTML using Twig
        $htmlContent = $this->twig->render('delivery/delivery_report.html.twig', [
            'deliveries' => $reportData
        ]);

        // Add files to zip
        $zip->addFromString('report.csv', $csv->toString());
        $zip->addFromString('report.html', $htmlContent);

        $zip->close();
        return $zipName;

    }

    private function setupZip($deliveries, \ZipArchive $zip): array
    {
        $reportData = [];

        /* @var $delivery Delivery */
        foreach ($deliveries as $delivery) {
            /* @var $order ?Order */
            $order = $delivery->getOrder();

            /* @var $task Task */
            foreach ($delivery->getTasks() as $task) {
                if ($task->getType() === Task::TYPE_DROPOFF) {
                    $podPaths = [];

                    // Add task images (PODs) to archive
                    foreach ($task->getImages() as $image) {
                        $path = $this->resolvePath($image);
                        if ($path) {
                            $zipPath = sprintf('/delivery_%s/task_%s/pod_%s', $delivery->getId(), $task->getId(), basename($path));
                            $zip->addFromString(
                                $zipPath,
                                $this->taskImagesFilesystem->read($path)
                            );
                            $podPaths[] = $zipPath;
                        }
                    }

                    // Add incident images to archive
                    foreach ($task->getIncidents() as $incident) {
                        $incidentFolder = sprintf('/delivery_%s/task_%s/incident_%s', $delivery->getId(), $task->getId(), $incident->getId());

                        foreach ($incident->getImages() as $image) {
                            $path = $this->resolvePath($image);
                            if ($path) {
                                $zipPath = sprintf('%s/incident_image_%s', $incidentFolder, basename($path));
                                $zip->addFromString(
                                    $zipPath,
                                    $this->incidentImagesFilesystem->read($path)
                                );
                                $podPaths[] = $zipPath;
                            }
                        }

                        // Add incident info file
                        $zip->addFromString(
                            sprintf('%s/infos.txt', $incidentFolder),
                            <<<TXT
                            Incident: {$incident->getFailureReasonCode()}
                            Description: {$incident->getDescription()}
                            Reported at: {$incident->getCreatedAt()->format('Y-m-d H:i:s')}
                            TXT
                        );
                    }

                    // Build single data structure for both outputs
                    $reportData[] = [
                        'delivery_id' => $delivery->getId(),
                        'delivery_url' => $this->urlGenerator->generate('dashboard_delivery', ['id' => $delivery->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                        'order_number' => $order?->getNumber() ?? '',
                        'recipient' => $task->getAddress()->getName(),
                        'status' => $task->getStatus(),
                        'comment' => $this->getTaskComment($task) ?? '',
                        'pod_paths' => $podPaths,
                        'incidents' => count($task->getIncidents())
                    ];
                }
            }
        }

        return $reportData;
    }

    private function getTaskComment(Task $task): ?string
    {
        $event = $task->getEvents()->filter(function (TaskEvent $event) {
            return $event->getName() === 'task:done';
        })->first();
        if ($event) {
            return $event->getData('notes');
        }
        return null;
    }

    /**
     * @param TaskImage|IncidentImage $image
     */
    private function resolvePath($image): ?string
    {
        $path = ltrim($this->storage->resolveUri($image, 'file'), '/');

        if ($image instanceof TaskImage) {
            if ($this->taskImagesFilesystem->fileExists($path))
            {
                return $path;
            }
        }


        if ($image instanceof IncidentImage) {
            if ($this->incidentImagesFilesystem->fileExists($path))
            {
                return $path;
            }
        }

        return null;
    }
}
