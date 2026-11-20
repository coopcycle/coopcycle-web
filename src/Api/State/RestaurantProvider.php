<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Doctrine\EntityPreloader\LocalBusinessPreloader;
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\RestaurantFilter;
use AppBundle\Utils\SortableRestaurantIterator;

final class RestaurantProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $provider,
        private RestaurantFilter $restaurantFilter,
        private TimingRegistry $timingRegistry,
        private LocalBusinessPreloader $preloader,
        private Pagination $pagination)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Which shops deliver to an address, and the order they come back in,
        // are both decided here in PHP rather than by the database: the first
        // asks the routing engine for a distance per shop, the second reads
        // each shop's next slot out of the timing cache. Neither can be
        // expressed as SQL, so the whole set is fetched and then paginated as
        // an array. Pagination keeps the payload small; it does not spare the
        // server the work.
        $collection = $this->provider->provide(
            $operation->withPaginationEnabled(false),
            $uriVariables,
            $context
        );

        // Preload entities to optimize N+1 queries
        $this->preloader->preload($collection);

        $hasCoordinateFilter = isset($context['filters']) && isset($context['filters']['coordinate']);

        if ($hasCoordinateFilter) {
            [ $latitude, $longitude ] = explode(',', $context['filters']['coordinate']);
            $collection = $this->restaurantFilter->matchingLatLng($collection, $latitude, $longitude);
        }

        $iterator = new SortableRestaurantIterator($collection, $this->timingRegistry);
        $shops = iterator_to_array($iterator);

        if (!$this->pagination->isEnabled($operation, $context)) {
            return $shops;
        }

        [, $offset, $limit] = $this->pagination->getPagination($operation, $context);

        return new ArrayPaginator($shops, $offset, $limit);
    }
}
