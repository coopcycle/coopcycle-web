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

class ShopsEventsForTypesenseSubscriber implements EventSubscriber
{
    const COLLECTION = 'shops';

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
                "cuisine" => $this->getShopCuisines($entity),
                "category" => $this->getShopCategories($entity),
                "enabled" => $entity->isEnabled(),
            ];
            $this->typesenseShopsClient->createDocument($document);
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
                "cuisine" => $this->getShopCuisines($entity),
                "category" => $this->getShopCategories($entity),
                "enabled" => $entity->isEnabled(),
            ];
            $this->typesenseShopsClient->updateDocument($id, $document);
        }
    }

    public function postSoftDelete(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof LocalBusiness) {
            $id = strval($entity->getId());
            $this->typesenseShopsClient->deleteDocument($id);
        }
    }

    private function getShopCuisines($shop)
    {
        $isFoodEstablishment = FoodEstablishment::isValid($shop->getType());

        if (!$isFoodEstablishment) {
            return [];
        }

        $cuisines = [];
        foreach($shop->getServesCuisine() as $c) {
            $cuisines[] = $c->getName();
        }

        return $cuisines;
    }

    private function getShopCategories($shop)
    {
        $categories = [];

        if ($shop->isFeatured()) {
            $categories[] = 'featured';
        }

        if ($shop->isExclusive()) {
            $categories[] = 'exclusive';
        }

        if ($shop->isDepositRefundEnabled() || $shop->isLoopeatEnabled()) {
            $categories[] = 'zerowaste';
        }

        return $categories;
    }
 }
