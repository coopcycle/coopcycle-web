<?php

namespace AppBundle\EventListener\Upload;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductImage;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\ImportTasks;
use AppBundle\Spreadsheet\ProductSpreadsheetParser;
use AppBundle\Service\SettingsManager;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use AppBundle\Validator\Constraints\Spreadsheet as AssertSpreadsheet;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Oneup\UploaderBundle\Event\PostPersistEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;

final class UploadListener
{
    private $entityManager;
    private $mappingFactory;
    private $uploadHandler;
    private $settingsManager;
    private $messageBus;
    private $productSpreadsheetParser;
    private $secret;
    private $isDemo;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        PropertyMappingFactory $mappingFactory,
        UploadHandler $uploadHandler,
        SettingsManager $settingsManager,
        MessageBusInterface $messageBus,
        ProductSpreadsheetParser $productSpreadsheetParser,
        SerializerInterface $serializer,
        IriConverterInterface $iriConverter,
        CacheInterface $projectCache,
        ValidatorInterface $validator,
        string $secret,
        bool $isDemo,
        LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->mappingFactory = $mappingFactory;
        $this->uploadHandler = $uploadHandler;
        $this->settingsManager = $settingsManager;
        $this->messageBus = $messageBus;
        $this->productSpreadsheetParser = $productSpreadsheetParser;
        $this->serializer = $serializer;
        $this->iriConverter = $iriConverter;
        $this->projectCache = $projectCache;
        $this->validator = $validator;
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

                $violations = $this->validator->validate($file, new AssertSpreadsheet('product'));
                if (count($violations) > 0) {
                    throw new \Exception((string) $violations);
                }

                $restaurant = $this->iriConverter->getItemFromIri($request->get('restaurant'));

                $products = $this->productSpreadsheetParser->parse($file);
                foreach ($products as $product) {
                    $restaurant->addProduct($product);
                }

                $this->entityManager->flush();

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

        if ($type === 'restaurant' || $type === 'restaurant_banner') {
            $object = $this->entityManager->getRepository(LocalBusiness::class)->find(
                $request->get('id')
            );
            // Remove previous file
            $this->uploadHandler->remove($object, $type === 'restaurant_banner' ? 'bannerImageFile' : 'imageFile');
        } elseif ($type === 'product') {
            $product = $this->entityManager->getRepository(Product::class)->find(
                $request->get('id')
            );

            $object = new ProductImage();
            $object->setRatio($request->get('ratio', '1:1'));

            $product->addImage($object);

        } else {
            return;
        }

        // Update image_name column in database
        if ($type === 'restaurant_banner') {
            $object->setBannerImageName($file->getBasename());
        } else {
            $object->setImageName($file->getBasename());
        }

        $this->entityManager->flush();

        // Invoke VichUploaderBundle's directory namer
        $propertyMapping = $this->mappingFactory->fromField($object, $type === 'restaurant_banner' ? 'bannerImageFile' : 'imageFile');
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

        $this->projectCache->delete('content.company_logo.base_64');
    }

    private function onTasksUpload(PostPersistEvent $event)
    {
        $file = $event->getFile();
        $fileSystem = $file->getFilesystem();

        $request = $event->getRequest();
        $response = $event->getResponse();

        $mimeType = $file->getMimeType();

        $violations = $this->validator->validate($file, new AssertSpreadsheet('task'));
        if (count($violations) > 0) {
            $fileSystem->delete($file->getPathname());

            throw new UploadException((string) $violations);
        }

        $date = $request->get('date');
        $hashids = new Hashids($this->secret, 8);

        $encoded = $hashids->encode(mt_rand());
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

        $this->projectCache->delete('banner_svg_stat');
        $this->projectCache->delete('banner_svg');
    }
}
