<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Entity\Delivery;
use AppBundle\Action\Base;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use AppBundle\Service\TaskManager;
use DeliveryPODExportInput;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

class PODExport extends Base
{

    private readonly DeliveryRepository $deliveryRepository;

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly Filesystem $taskImagesFilesystem,
        private readonly UrlGeneratorInterface $urlGenerator,
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

    private function buildZip($deliveries)
    {
        $zip = new \ZipArchive();
        $zipName = tempnam(sys_get_temp_dir(), 'coopcycle_store_pods');
        $zip->open($zipName, \ZipArchive::CREATE);

        $csv = Writer::createFromString();
        $csv->insertOne(['delivery', 'order_number', 'recipient', 'status', 'pods']);

        /* @var $delivery Delivery */
        foreach ($deliveries as $delivery) {

            /* @var $order ?Order */
            $order = $delivery->getOrder();

            /* @var $task Task */
            foreach ($delivery->getTasks() as $task) {
                $podPaths = [];
                if ($task->getType() === Task::TYPE_DROPOFF) {
                    /* @var $image TaskImage */
                    foreach ($task->getImages() as $image) {
                        $path = $this->resolvePath($image);
                        if ($path) {
                            $zipPath = sprintf('/%s/%s/%s', $delivery->getId(), $task->getId(), basename($path));
                            $zip->addFromString(
                                $zipPath,
                                $this->taskImagesFilesystem->read($path)
                            );
                            $podPaths[] = $zipPath;
                        }
                    }
                    $csv->insertOne([
                        $this->urlGenerator->generate('dashboard_delivery', ['id' => $delivery->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                        $order?->getNumber(),
                        $task->getAddress()->getName(),
                        $task->getStatus(),
                        implode("\n", $podPaths),
                    ]);
                }
            }
        }

        $zip->addFromString('report.csv', $csv->toString());

        $zip->close();
        return $zipName;

    }

    private function resolvePath(TaskImage $image): ?string
    {
        $path = $this->storage->resolveUri($image, 'file');
        if ($this->taskImagesFilesystem->fileExists($path))
        {
            return $path;
        }
        return null;
    }
}
