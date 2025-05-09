<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\SortableRestaurantIterator;
use AppBundle\Utils\RestaurantFilter;

final class RestaurantProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $provider,
        private RestaurantFilter $restaurantFilter,
        private TimingRegistry $timingRegistry)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        $collection = $this->provider->provide($operation, $uriVariables, $context);

        $hasCoordinateFilter = isset($context['filters']) && isset($context['filters']['coordinate']);

        if ($hasCoordinateFilter) {
            [ $latitude, $longitude ] = explode(',', $context['filters']['coordinate']);
            $collection = $this->restaurantFilter->matchingLatLng($collection, $latitude, $longitude);
        }

        $iterator = new SortableRestaurantIterator($collection, $this->timingRegistry);

        return iterator_to_array($iterator);
    }
}
