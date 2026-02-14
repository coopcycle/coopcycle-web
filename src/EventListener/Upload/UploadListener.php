<?php

namespace AppBundle\EventListener\Upload;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductImage;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\ImportTasks;
use AppBundle\Spreadsheet\ProductSpreadsheetParser;
use AppBundle\Service\SettingsManager;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use AppBundle\Utils\ValidationUtils;
use AppBundle\Validator\Constraints\Spreadsheet as AssertSpreadsheet;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Liip\ImagineBundle\Service\FilterService;
use Oneup\UploaderBundle\Event\PostPersistEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;

final class UploadListener
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyMappingFactory $mappingFactory,
        private readonly UploadHandler $uploadHandler,
        private readonly SettingsManager $settingsManager,
        private readonly MessageBusInterface $messageBus,
        private readonly ProductSpreadsheetParser $productSpreadsheetParser,
        private readonly IriConverterInterface $iriConverter,
        private readonly CacheInterface $appCache,
        private readonly ValidatorInterface $validator,
        private readonly FilterService $imagineFilter,
        private readonly string $secret,
        private readonly bool $isDemo,
        private readonly LoggerInterface $logger)
    {
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
                    throw new \Exception(ValidationUtils::serializeToString($violations));
                }

                $restaurant = $this->iriConverter->getResourceFromIri($request->get('restaurant'));

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

        if ($type === 'homepage_slide') {
            return $this->onHomepageSlideUpload($event);
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

        $file->getFilesystem()->move(
            $file->getPathname(),
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

        $violations = $this->validator->validate($file, new AssertSpreadsheet('task'));
        if (count($violations) > 0) {
            $fileSystem->delete($file->getPathname());

            throw new UploadException(ValidationUtils::serializeToString($violations));
        }

        $date = $request->get('date');
        $hashids = new Hashids($this->secret, 8);

        $encoded = $hashids->encode(mt_rand());
        $this->logger->debug(sprintf('UploadListener | hashid = %s', $encoded));

        $filename = sprintf('%s.%s', $encoded, TaskSpreadsheetParser::getFileExtension($mimeType));
        $this->logger->debug(sprintf('UploadListener | filename = %s', $filename));

        $fileSystem->move($file->getPathname(), $filename);

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
        $event->getFile();

        if ($this->isDemo) {
            throw new UploadException('Banner can\'t be changed in demo mode');
        }

        $this->appCache->delete('banner_svg_stat');
        $this->appCache->delete('banner_svg');
    }

    private function onHomepageSlideUpload(PostPersistEvent $event)
    {
        $file = $event->getFile();
        $fileSystem = $file->getFilesystem();

        if ($this->isDemo) {
            throw new UploadException('Slides can\'t be uploaded in demo mode');
        }

        $url = $this->imagineFilter->getUrlOfFilteredImage($file->getPathname(), 'homepage_slider_images_thumbnail');

        $response = $event->getResponse();
        $response['url'] = $url;

        return $response;
    }
}
