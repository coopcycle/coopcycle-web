<?php

namespace AppBundle\Doctrine\EventSubscriber;

use ACSEO\TypesenseBundle\Manager\CollectionManager;
use ACSEO\TypesenseBundle\Manager\DocumentManager;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Typesense\Exceptions\ObjectNotFound;

class ShopsEventsForTypesenseSubscriber implements EventSubscriber
{
    public function __construct(
        CollectionManager $collectionManager,
        DocumentManager $documentManager,
        LoggerInterface $logger
    )
    {
        $this->logger = $logger;
        $this->collectionManager = $collectionManager;
        $this->documentManager = $documentManager;
    }

    public function getSubscribedEvents()
    {
        return array(
            SoftDeleteableListener::POST_SOFT_DELETE,
        );
    }

    public function postSoftDelete(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof LocalBusiness) {

            $collectionDefinitions = $this->collectionManager->getCollectionDefinitions();

            foreach ($collectionDefinitions as $collectionDefinition) {

                $collectionName = $collectionDefinition['typesense_name'];

                if ($collectionDefinition['entity'] === LocalBusiness::class) {
                    $id = strval($entity->getId());
                    try {
                        $this->documentManager->delete($collectionName, $id);
                    } catch (ObjectNotFound $e) {
                        $this->logger->error($e->getMessage());
                    }
                }
            }
        }
    }
 }
