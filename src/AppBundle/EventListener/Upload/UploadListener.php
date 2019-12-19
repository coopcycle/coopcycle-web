<?php

namespace AppBundle\EventListener\Upload;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Service\SettingsManager;
use Doctrine\Persistence\ManagerRegistry;
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
        SettingsManager $settingsManager,
        LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->mappingFactory = $mappingFactory;
        $this->uploadHandler = $uploadHandler;
        $this->settingsManager = $settingsManager;
        $this->logger = $logger;
    }

    public function onUpload(PostPersistEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $file = $event->getFile();

        $type = $request->get('type');

        if ($type === 'logo') {
            return $this->onLogoUpload($event);
        }

        $objectClass = null;
        if ($type === 'restaurant') {
            $objectClass = Restaurant::class;
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

        $this->settingsManager->set('company_logo', $file->getBasename());
        $this->settingsManager->flush();
    }
}
