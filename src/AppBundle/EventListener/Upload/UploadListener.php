<?php

namespace AppBundle\EventListener\Upload;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Service\SettingsManager;
use Doctrine\Common\Persistence\ManagerRegistry;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Oneup\UploaderBundle\Event\PostPersistEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;

final class UploadListener
{
    private $doctrine;
    private $mappingFactory;
    private $uploadHandler;
    private $logger;
    private $restaurantImagesLoader;
    private $productImagesLoader;
    private $settingsManager;
    private $filterManager;
    private $filesystem;

    public function __construct(
        ManagerRegistry $doctrine,
        PropertyMappingFactory $mappingFactory,
        UploadHandler $uploadHandler,
        FilterManager $filterManager,
        LoaderInterface $restaurantImagesLoader,
        LoaderInterface $productImagesLoader,
        SettingsManager $settingsManager,
        Filesystem $filesystem,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->mappingFactory = $mappingFactory;
        $this->uploadHandler = $uploadHandler;
        $this->filterManager = $filterManager;
        $this->restaurantImagesLoader = $restaurantImagesLoader;
        $this->productImagesLoader = $productImagesLoader;
        $this->settingsManager = $settingsManager;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    public function onUpload(PostPersistEvent $event)
    {
        $request = $event->getRequest();

        $type = $request->get('type');

        if ($type === 'logo') {

            return $this->onLogoUpload($event);
        }

        $response = $event->getResponse();
        $file = $event->getFile();
        $config = $event->getConfig();

        $id = $request->get('id');

        $objectClass = null;
        if ($type === 'restaurant') {
            $objectClass = Restaurant::class;
            $imagesLoader = $this->restaurantImagesLoader;
            $filterName = 'restaurant_thumbnail';
        } elseif ($type === 'product') {
            $objectClass = Product::class;
            $imagesLoader = $this->productImagesLoader;
            $filterName = 'product_thumbnail';
        } else {
            return;
        }

        $object = $this->doctrine->getRepository($objectClass)->find($id);

        // Remove previous file
        $this->uploadHandler->remove($object, 'imageFile');

        // Update image_name column in database
        $object->setImageName($file->getFilename());
        $this->doctrine->getManagerForClass($objectClass)->flush();

        // Invoke VichUploaderBundle's directory namer
        $propertyMapping = $this->mappingFactory->fromField($object, 'imageFile');
        $directoryNamer = $propertyMapping->getDirectoryNamer();

        $directoryName = $directoryNamer->directoryName($object, $propertyMapping);
        $targetDir = sprintf('%s/%s', $config['storage']['directory'], $directoryName);

        $targetFile = $file->move($targetDir);

        try {

            // Optimize image
            $relativePathName = sprintf('%s/%s', $directoryName, $targetFile->getFilename());
            $image = $imagesLoader->find($relativePathName);
            $filteredBinary = $this->filterManager->applyFilter($image, $filterName);

            // Overwrite uploaded file
            $this->filesystem->dumpFile($targetFile->getRealPath(), $filteredBinary->getContent());

        } catch (NotLoadableException $e) {
            $this->logger->error('An error occured while post-processing uploaded image');
        }
    }

    private function onLogoUpload(PostPersistEvent $event)
    {
        $file = $event->getFile();
        $config = $event->getConfig();

        $targetFile = $file->move($config['storage']['directory'], 'logo.png');

        $this->settingsManager->set('custom_logo', 'yes', 'appearance');
        $this->settingsManager->flush();
    }
}
