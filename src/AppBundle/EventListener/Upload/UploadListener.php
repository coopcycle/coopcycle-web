<?php

namespace AppBundle\EventListener\Upload;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Product;
use Doctrine\Common\Persistence\ManagerRegistry;
use Oneup\UploaderBundle\Event\PostPersistEvent;
use Psr\Log\LoggerInterface;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;

final class UploadListener
{
    private $doctrine;
    private $mappingFactory;
    private $uploadHandler;
    private $logger;

    public function __construct(
        ManagerRegistry $doctrine,
        PropertyMappingFactory $mappingFactory,
        UploadHandler $uploadHandler,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->mappingFactory = $mappingFactory;
        $this->uploadHandler = $uploadHandler;
        $this->logger = $logger;
    }

    public function onUpload(PostPersistEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $file = $event->getFile();

        $type = $request->get('type');
        $id = $request->get('id');

        $objectClass = null;
        if ($type === 'restaurant') {
            $objectClass = Restaurant::class;
        } elseif ($type === 'product') {
            $objectClass = Product::class;
        } else {
            return;
        }

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
}
