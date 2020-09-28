<?php

namespace AppBundle\EventListener\Upload;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\ImportTasks;
use AppBundle\Spreadsheet\ProductSpreadsheetParser;
use AppBundle\Service\SettingsManager;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Doctrine\Persistence\ManagerRegistry;
use Hashids\Hashids;
use Oneup\UploaderBundle\Event\PostPersistEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Serializer\SerializerInterface;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;

final class UploadListener
{
    private $doctrine;
    private $mappingFactory;
    private $uploadHandler;
    private $settingsManager;
    private $messageBus;
    private $productSpreadsheetParser;
    private $secret;
    private $isDemo;
    private $logger;

    public function __construct(
        ManagerRegistry $doctrine,
        PropertyMappingFactory $mappingFactory,
        UploadHandler $uploadHandler,
        SettingsManager $settingsManager,
        MessageBusInterface $messageBus,
        ProductSpreadsheetParser $productSpreadsheetParser,
        SerializerInterface $serializer,
        IriConverterInterface $iriConverter,
        CacheInterface $appCache,
        string $secret,
        bool $isDemo,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->mappingFactory = $mappingFactory;
        $this->uploadHandler = $uploadHandler;
        $this->settingsManager = $settingsManager;
        $this->messageBus = $messageBus;
        $this->productSpreadsheetParser = $productSpreadsheetParser;
        $this->serializer = $serializer;
        $this->iriConverter = $iriConverter;
        $this->appCache = $appCache;
        $this->secret = $secret;
        $this->isDemo = $isDemo;
        $this->logger = $logger;
    }

    public function onUpload(PostPersistEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $file = $event->getFile();

        if ('products' === $event->getType()) {

            try {

                $restaurant = $this->iriConverter->getItemFromIri($request->get('restaurant'));

                $products = $this->productSpreadsheetParser->parse($file);
                foreach ($products as $product) {
                    $restaurant->addProduct($product);
                }

                $this->doctrine->getManagerForClass(LocalBusiness::class)->flush();

                $file->getFilesystem()->delete($file->getPathname());

            } catch (\Exception $e) {

                $file->getFilesystem()->delete($file->getPathname());

                throw new UploadException($e->getMessage());
            }

            // $response['products'] = $this->serializer->normalize($products, 'json', ['iri' => '']);

            return $response;
        }

        if ('banner' === $event->getType()) {
            return $this->onBannerUpload($event);
        }

        $type = $request->get('type');

        if ($type === 'logo') {
            return $this->onLogoUpload($event);
        }

        if ($type === 'tasks') {
            return $this->onTasksUpload($event);
        }

        $objectClass = null;
        if ($type === 'restaurant') {
            $objectClass = LocalBusiness::class;
        } elseif ($type === 'product') {
            $objectClass = Product::class;
        } else {
            return;
        }

        $id = $request->get('id');
        $object = $this->doctrine->getRepository($objectClass)->find($id);

        // Remove previous file
        $this->uploadHandler->remove($object, 'imageFile');

        // Update image_name column in database
        $object->setImageName($file->getBasename());
        $this->doctrine->getManagerForClass($objectClass)->flush();

        // Invoke VichUploaderBundle's directory namer
        $propertyMapping = $this->mappingFactory->fromField($object, 'imageFile');
        $directoryNamer = $propertyMapping->getDirectoryNamer();

        $directoryName = $directoryNamer->directoryName($object, $propertyMapping);

        $file->getFilesystem()->rename(
            $file->getPath(),
            sprintf('%s/%s', $directoryName, $file->getBasename())
        );
    }

    private function onLogoUpload(PostPersistEvent $event)
    {
        $file = $event->getFile();

        if ($this->isDemo) {
            throw new UploadException('Company logo can\'t be changed in demo mode');
        }

        $this->settingsManager->set('company_logo', $file->getBasename());
        $this->settingsManager->flush();

        $this->appCache->delete('content.company_logo.base_64');
    }

    private function onTasksUpload(PostPersistEvent $event)
    {
        $file = $event->getFile();
        $fileSystem = $file->getFilesystem();

        $request = $event->getRequest();
        $response = $event->getResponse();

        $mimeType = $file->getMimeType();

        $this->logger->debug(sprintf('UploadListener | file = %s', $file->getPathname()));
        $this->logger->debug(sprintf('UploadListener | mime = %s', $mimeType));

        // For CSV files, we need to make sure they are in UTF-8
        if (in_array($mimeType, ['text/csv', 'text/plain'])) {

            // Make sure the file is in UTF-8
            $encoding = mb_detect_encoding($fileSystem->read($file->getPathname()), 'UTF-8', true);

            $this->logger->debug(sprintf('UploadListener | encoding = %s', var_export($encoding, true)));

            if ($encoding !== 'UTF-8') {
                $fileSystem->delete($file->getPathname());

                throw new UploadException('CSV files must be encoded in UTF-8');
            }
        }

        $date = $request->get('date');
        $hashids = new Hashids($this->secret, 8);

        $taskGroup = new TaskGroup();
        $taskGroup->setName(sprintf('Import %s', date('d/m H:i')));

        // The TaskGroup will serve as a unique identifier
        $this->doctrine
            ->getManagerForClass(TaskGroup::class)
            ->persist($taskGroup);
        $this->doctrine
            ->getManagerForClass(TaskGroup::class)
            ->flush();

        $encoded = $hashids->encode($taskGroup->getId());
        $this->logger->debug(sprintf('UploadListener | hashid = %s', $encoded));

        $filename = sprintf('%s.%s', $encoded, TaskSpreadsheetParser::getFileExtension($mimeType));
        $this->logger->debug(sprintf('UploadListener | filename = %s', $filename));

        $fileSystem->rename($file->getPathname(), $filename);

        $this->messageBus->dispatch(
            new ImportTasks($encoded, $filename, new \DateTime($date)),
            [ new DelayStamp(15000) ]
        );

        $response['token'] = $encoded;
        $response['success'] = true;
        // $response['error'] = 'Bad encoding';

        return $response;
    }

    private function onBannerUpload(PostPersistEvent $event)
    {
        $file = $event->getFile();

        if ($this->isDemo) {
            throw new UploadException('Banner can\'t be changed in demo mode');
        }

        $this->appCache->delete('banner_svg_stat');
        $this->appCache->delete('banner_svg');
    }
}
