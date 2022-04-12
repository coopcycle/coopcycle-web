<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\CollectionDataProvider;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\SortableRestaurantIterator;
use AppBundle\Utils\RestaurantFilter;

final class RestaurantCollectionDataProvider extends CollectionDataProvider
{
    private $restaurantFilter;
    private $timingRegistry;

    public function setRestaurantFilter(RestaurantFilter $restaurantFilter)
    {
        $this->restaurantFilter = $restaurantFilter;
    }

    public function setTimingRegistry(TimingRegistry $timingRegistry)
    {
        $this->timingRegistry = $timingRegistry;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return LocalBusiness::class === $resourceClass && $operationName === 'get';
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $collection = parent::getCollection($resourceClass, $operationName, $context);

        $hasCoordinateFilter = isset($context['filters']) && isset($context['filters']['coordinate']);

        if ($hasCoordinateFilter) {
            [ $latitude, $longitude ] = explode(',', $context['filters']['coordinate']);
            $collection = $this->restaurantFilter->matchingLatLng($collection, $latitude, $longitude);
        }

        $iterator = new SortableRestaurantIterator($collection, $this->timingRegistry);

        return iterator_to_array($iterator);
    }
}
