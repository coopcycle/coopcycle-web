<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use AppBundle\Typesense\ShopsClient as TypesenseShopsClient;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Typesense\Exceptions\ObjectNotFound;

class ShopsEventsForTypesenseSubscriber implements EventSubscriber
{
    public function __construct(
        LoggerInterface $logger,
        TypesenseShopsClient $typesenseShopsClient
    )
    {
        $this->logger = $logger;
        $this->typesenseShopsClient = $typesenseShopsClient;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::postPersist,
            Events::postUpdate,
            SoftDeleteableListener::POST_SOFT_DELETE,
        );
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof LocalBusiness) {
            $document = [
                "id" => strval($entity->getId()), // index with same ID so we can query by ID in database after a selection
                "name" => $entity->getName(),
                "type" => LocalBusiness::getKeyForType($entity->getType()),
                "cuisine" => $entity->getShopCuisines(),
                "category" => $entity->getShopCategories(),
                "enabled" => $entity->isEnabled(),
            ];
            try {
                $this->typesenseShopsClient->createDocument($document);
            } catch (ObjectNotFound $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof LocalBusiness) {
            if ($entity->isDeleted()) {
                // ignore this update because is handled properly by postSoftDelete
                return;
            }

            $id = strval($entity->getId());
            $document = [
                "name" => $entity->getName(),
                "type" => LocalBusiness::getKeyForType($entity->getType()),
                "cuisine" => $entity->getShopCuisines(),
                "category" => $entity->getShopCategories(),
                "enabled" => $entity->isEnabled(),
            ];
            try {
                $this->typesenseShopsClient->updateDocument($id, $document);
            } catch (ObjectNotFound $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    public function postSoftDelete(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof LocalBusiness) {
            $id = strval($entity->getId());
            try {
                $this->typesenseShopsClient->deleteDocument($id);
            } catch (ObjectNotFound $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }
 }
