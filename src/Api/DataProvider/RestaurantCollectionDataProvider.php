<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\CollectionDataProvider;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Utils\SortableRestaurantIterator;
use AppBundle\Utils\RestaurantFilter;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

final class RestaurantCollectionDataProvider extends CollectionDataProvider
{
    private $restaurantFilter;

    public function __construct(
        ManagerRegistry $managerRegistry,
        iterable $collectionExtensions = [],
        RestaurantFilter $restaurantFilter)
    {
        parent::__construct($managerRegistry, $collectionExtensions);

        $this->restaurantFilter = $restaurantFilter;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return LocalBusiness::class === $resourceClass && $operationName === 'get';
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $collection = parent::getCollection($resourceClass, $operationName, $context);

        $hasCoordinateFilter = isset($context['filters']) && isset($context['filters']['coordinate']);

        if ($hasCoordinateFilter) {
            [ $latitude, $longitude ] = explode(',', $context['filters']['coordinate']);
            $collection = $this->restaurantFilter->matchingLatLng($collection, $latitude, $longitude);
        }

        $iterator = new SortableRestaurantIterator($collection);

        return iterator_to_array($iterator);
    }
}
