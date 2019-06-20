<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\CollectionDataProvider;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Utils\RestaurantFilter;
use Doctrine\Common\Persistence\ManagerRegistry;

final class RestaurantCollectionDataProvider extends CollectionDataProvider
{
    private $restaurantFilter;

    public function __construct(
        ManagerRegistry $managerRegistry,
        /* iterable */ $collectionExtensions = [],
        RestaurantFilter $restaurantFilter)
    {
        // Pop the PaginationExtension

        // ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\EagerLoadingExtension
        // ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\FilterExtension
        // ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\FilterEagerLoadingExtension
        // ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\OrderExtension
        // ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\PaginationExtension
        $extensions = [];
        foreach ($collectionExtensions as $key => $extension) {
            // We remove the PaginationExtension
            if ($extension instanceof QueryResultCollectionExtensionInterface) {
                continue;
            }
            $extensions[] = $extension;
        }

        parent::__construct($managerRegistry, $extensions);

        $this->restaurantFilter = $restaurantFilter;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        $supports = false;
        if (Restaurant::class === $resourceClass && $operationName === 'get') {
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
