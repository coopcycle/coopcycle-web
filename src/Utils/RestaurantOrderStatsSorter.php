<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\OrderRepository;

class RestaurantOrderStatsSorter
{
    public function __construct(private OrderRepository $orderRepository)
    {}

    /**
     * @param LocalBusiness[] $shops
     * @param string $mode One of 'historical_order_volume', 'ordering_potential', 'popularity'
     * @return LocalBusiness[]
     */
    public function sort(array $shops, string $mode): array
    {
        $ids = array_map(fn(LocalBusiness $shop) => $shop->getId(), $shops);

        $counts = match ($mode) {
            'historical_order_volume' => $this->orderRepository->countFulfilledOrdersByRestaurants($ids),
            'ordering_potential' => $this->orderRepository->countFulfilledOrdersByRestaurants($ids, new \DateTime('-30 days')),
            'popularity' => $this->orderRepository->countDistinctCustomersByRestaurants($ids),
            default => [],
        };

        usort($shops, fn(LocalBusiness $a, LocalBusiness $b) =>
            ($counts[$b->getId()] ?? 0) <=> ($counts[$a->getId()] ?? 0)
        );

        return $shops;
    }
}
