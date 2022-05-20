<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Typesense\Client as TypesenseClient;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

class ShopsEventsForTypesenseSubscriber implements EventSubscriber
{
    const COLLECTION = 'shops';

    public function __construct(
        LoggerInterface $logger,
        TypesenseClient $typesenseClient
    )
    {
        $this->logger = $logger;
        $this->typesenseClient = $typesenseClient;
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
            $this->typesenseClient->collections[self::COLLECTION]->documents->create($document);
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
            $this->typesenseClient->collections[self::COLLECTION]->documents[$id]->update($document);
        }
    }

    public function postSoftDelete(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof LocalBusiness) {
            $id = strval($entity->getId());
            $this->typesenseClient->collections[self::COLLECTION]->documents[$id]->delete();
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
