<?php

namespace AppBundle\Action\Search;

use ACSEO\TypesenseBundle\Client\CollectionClient;
use ACSEO\TypesenseBundle\Finder\TypesenseQuery;
use ACSEO\TypesenseBundle\Manager\CollectionManager;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use ApiPlatform\OpenApi\OpenApi;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class ShopsProducts
{
    public function __construct(
        private CollectionClient $collectionClient,
        private CollectionManager $collectionManager,
        private LoggerInterface $logger,
    )
    {}

    public function __invoke(Request $request)
    {
        // When the endpoint is opened in a browser, API Platform renders Swagger UI instead:
        // its provider stores the OpenApi documentation in the "data" attribute,
        // and its processor expects to receive it back (like PlaceholderAction does)
        $data = $request->attributes->get('data');
        if ($data instanceof OpenApi) {
            return $data;
        }

        $q = trim((string) $request->query->get('q'));

        if (empty($q)) {
            return [];
        }

        $managedClassNames = $this->collectionManager->getManagedClassNames();

        $shopsCollectionName = (string) array_search(LocalBusiness::class, $managedClassNames, true);
        $productsCollectionName = (string) array_search(Product::class, $managedClassNames, true);

        // https://github.com/acseo/TypesenseBundle#perform-multisearch
        $searchRequests = [
            (new TypesenseQuery($q, 'name,cuisine'))
                ->filterBy('enabled:true')
                ->addParameter('collection', $shopsCollectionName),
            (new TypesenseQuery($q, 'name'))
                ->filterBy('shop_enabled:true')
                ->addParameter('collection', $productsCollectionName)
        ];

        $response = $this->collectionClient->multisearch($searchRequests, null);

        if (is_null($response)) {
            $this->logger->warning('Typesense is not operational, returning no search results');

            return [];
        }

        [ $shopsResult, $productsResult ] = ($response['results'] ?? []) + [ [], [] ];

        $shopsDocuments = array_map(fn (array $hit) => array_merge(
            $hit['document'],
            ['result_type' => 'shop']
        ), $this->getHits($shopsResult, $shopsCollectionName));

        $productsDocuments = array_map(fn (array $hit) => array_merge(
            $hit['document'],
            ['result_type' => 'product']
        ), $this->getHits($productsResult, $productsCollectionName));

        return array_merge(
            $shopsDocuments,
            $productsDocuments
        );
    }

    /**
     * In a multisearch, each search fails on its own (e.g. a missing collection):
     * its result is then {"error": "...", "code": 404}, without "hits".
     *
     * @param array<string,mixed> $result
     * @return array<int,array<string,mixed>>
     */
    private function getHits(array $result, string $collectionName): array
    {
        if (isset($result['error'])) {
            $this->logger->warning(
                sprintf('Typesense search on collection "%s" failed: %s', $collectionName, $result['error']),
                [
                    'collection' => $collectionName,
                    'code' => $result['code'] ?? null,
                    'error' => $result['error'],
                ]
            );
        }

        return $result['hits'] ?? [];
    }
}
