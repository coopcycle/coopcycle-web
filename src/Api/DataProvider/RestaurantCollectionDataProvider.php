<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\CollectionDataProvider;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use AppBundle\Entity\LocalBusiness;
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
        $supports = false;
        if (LocalBusiness::class === $resourceClass && $operationName === 'get') {
            $supports = isset($context['filters']) && isset($context['filters']['coordinate']);
        }

        return $supports;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $collection = parent::getCollection($resourceClass, $operationName, $context);

        [ $latitude, $longitude ] = explode(',', $context['filters']['coordinate']);

        return $this->restaurantFilter->matchingLatLng($collection, $latitude, $longitude);
    }
}
