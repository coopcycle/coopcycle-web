<?php

namespace AppBundle\Doctrine\EventSubscriber;

use ACSEO\TypesenseBundle\Manager\CollectionManager;
use ACSEO\TypesenseBundle\Manager\DocumentManager;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Enum\FoodEstablishment;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Typesense\Exceptions\ObjectNotFound;
use ACSEO\TypesenseBundle\Client\TypesenseClient;
use AppBundle\Entity\Store;

class ShopsEventsForTypesenseSubscriber implements EventSubscriber
{
    private $productsCollection;
    private $maxPosts = 250;

    public function __construct(
        private CollectionManager $collectionManager,
        private DocumentManager $documentManager,
        private LoggerInterface $logger,
        private TypesenseClient $typesenseClient)
    {
        $this->productsCollection = array_search(Product::class, $this->collectionManager->getManagedClassNames(), true);
    }

    public function getSubscribedEvents()
    {
        return array(
            SoftDeleteableListener::POST_SOFT_DELETE,
            Events::postUpdate,
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

    private function updateSearchProductsEnabledStatus($search, $enabled, $page = 1, )
    {
        $searchParameters = [
            'q'         => '*',
            'query_by'  => 'name', // TypeSense can't search for non string fields...
            'filter_by' => 'shop_id:=' . $search, //... so we need to filter
            'per_page' => $this->maxPosts, // max!
            'page' => $page,
        ];

        $sResults = $this->typesenseClient
            ->collections[$this->productsCollection]
            ->documents->search($searchParameters);

        // PAGINATION:
        // `$sResults['found']` is the total count of products found.
        // `$sResults['hits']` are the products returned (by the query) limited by `per_page`
        // `$sResults['page']` is the current results page

        $productsToUpsert = [];

        foreach ($sResults['hits'] as $sResult) {
            $product = $sResult['document'];
            $product['shop_enabled'] = $enabled;
            $productsToUpsert[] = $product;
        }

        try {
            $this->typesenseClient
                ->collections[$this->productsCollection]
                ->documents
                ->import($productsToUpsert, ['action' => 'upsert']);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            // bail early if there was an error, as next request will probably fail
            return;
        }

        // If there are more items than the already processed ones
        // call this method again with updated params
        if ($sResults['found'] > ($sResults['page'] * $this->maxPosts)) {
            $this->updateSearchProductsEnabledStatus($search, $enabled, $sResults['page'] + 1);
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof LocalBusiness) {
            $om = $args->getObjectManager();
            $uow = $om->getUnitOfWork();
            $uow->computeChangeSets();
            $changeset = $uow->getEntityChangeSet($entity);

            // LocalBusiness `enabled` status changed, so update it's products
            // enabled status on the search server.
            if (isset($changeset['enabled'])) {
                // the second value of the changeset is the new/current value
                $isShopEnabled = $changeset['enabled'][1];

                // recursive function that paginates and updates all shop's
                // products
                $this->updateSearchProductsEnabledStatus($entity->getId(), $isShopEnabled, 1);
            }
        }
    }
 }
