<?php

namespace AppBundle\Action\Search;

use ACSEO\TypesenseBundle\Client\CollectionClient;
use ACSEO\TypesenseBundle\Finder\CollectionFinderInterface;
use ACSEO\TypesenseBundle\Finder\TypesenseQuery;
use ACSEO\TypesenseBundle\Manager\CollectionManager;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use Symfony\Component\HttpFoundation\Request;

class ShopsProducts
{
    public function __construct(CollectionFinderInterface $typesenseShopsFinder, CollectionClient $collectionClient, CollectionManager $collectionManager)
    {
        $this->typesenseShopsFinder = $typesenseShopsFinder;
        $this->collectionClient = $collectionClient;
        $this->collectionManager = $collectionManager;
    }

    public function __invoke(Request $request)
    {
        $q = $request->query->get('q');

        $managedClassNames = $this->collectionManager->getManagedClassNames();

        // https://github.com/acseo/TypesenseBundle#perform-multisearch
        $searchRequests = [
            (new TypesenseQuery($q))
                ->addParameter('query_by', 'name,cuisine')
                ->filterBy('enabled:true')
                ->addParameter('collection', array_search(LocalBusiness::class, $managedClassNames, true)),
            (new TypesenseQuery($q))
                ->addParameter('query_by', 'name')
                ->filterBy('shop_enabled:true')
                ->addParameter('collection', array_search(Product::class, $managedClassNames, true))
        ];

        $response = $this->collectionClient->multisearch($searchRequests, null);

        [ $shopsResults, $productsResults ] = $response['results'];

        $shopsDocuments = array_map(function ($hit) {
            return array_merge(
                $hit['document'],
                ['result_type' => 'shop']
            );
        }, $shopsResults['hits']);

        $productsDocuments = array_map(function ($hit) {
            return array_merge(
                $hit['document'],
                ['result_type' => 'product']
            );
        }, $productsResults['hits']);

        return array_merge(
            $shopsDocuments,
            $productsDocuments
        );
    }
}
