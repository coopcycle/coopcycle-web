<?php

namespace AppBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use AppBundle\Entity\Restaurant;
use Doctrine\ORM\QueryBuilder;

use AppBundle\Entity\RestaurantRepository;

final class RestaurantFilter extends SearchFilter
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $properties = $this->extractProperties($request, Restaurant::class);
        $distance = isset($properties['distance']) ? $properties['distance'] : 3000;

        if (isset($properties['coordinate'])) {
            list($latitude, $longitude) = explode(',', $properties['coordinate']);
            RestaurantRepository::addNearbyQueryClause($queryBuilder, $latitude, $longitude, $distance);
        }
    }
}
