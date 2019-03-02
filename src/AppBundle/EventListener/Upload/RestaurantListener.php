<?php

namespace AppBundle\EventListener\Upload;

use AppBundle\Entity\Restaurant;
use Doctrine\Common\Persistence\ManagerRegistry;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Oneup\UploaderBundle\Event\PostPersistEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;

final class RestaurantListener
{
    private $doctrine;
    private $mappingFactory;
    private $uploadHandler;
    private $logger;
    private $restaurantImagesLoader;
    private $filterManager;
    private $filesystem;

    public function __construct(
        ManagerRegistry $doctrine,
        PropertyMappingFactory $mappingFactory,
        UploadHandler $uploadHandler,
        LoaderInterface $restaurantImagesLoader,
        FilterManager $filterManager,
        Filesystem $filesystem,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->mappingFactory = $mappingFactory;
        $this->uploadHandler = $uploadHandler;
        $this->restaurantImagesLoader = $restaurantImagesLoader;
        $this->filterManager = $filterManager;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    public function onUpload(PostPersistEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $file = $event->getFile();
        $config = $event->getConfig();

        $restaurantId = $request->get('restaurant');

        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($restaurantId);

        // Remove previous file
        $this->uploadHandler->remove($restaurant, 'imageFile');

        // Update image_name column in database
        $restaurant->setImageName($file->getFilename());
        $this->doctrine->getManagerForClass(Restaurant::class)->flush();

        // Invoke VichUploaderBundle's directory namer
        $propertyMapping = $this->mappingFactory->fromField($restaurant, 'imageFile');
        $directoryNamer = $propertyMapping->getDirectoryNamer();

        $directoryName = $directoryNamer->directoryName($restaurant, $propertyMapping);
        $targetDir = sprintf('%s/%s', $config['storage']['directory'], $directoryName);

        $targetFile = $file->move($targetDir);

        try {

            // Optimize image
            $relativePathName = sprintf('%s/%s', $directoryName, $targetFile->getFilename());
            $image = $this->restaurantImagesLoader->find($relativePathName);
            $filteredBinary = $this->filterManager->applyFilter($image, 'restaurant_thumbnail');

            // Overwrite uploaded file
            $this->filesystem->dumpFile($targetFile->getRealPath(), $filteredBinary->getContent());

        } catch (NotLoadableException $e) {
            $this->logger->error('An error occured while post-processing uploaded image');
        }
    }
}
