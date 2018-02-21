<?php

namespace AppBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\RestaurantRepository;
use Doctrine\ORM\QueryBuilder;

final class RestaurantFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $properties = $this->extractProperties($request, Restaurant::class);
        $distance = isset($properties['distance']) ? $properties['distance'] : 3000;

        if ($property === 'coordinate') {
            list($latitude, $longitude) = explode(',', $value);
            $this->logger->info(sprintf('RestaurantFilter :: %s, %s, %s', $latitude, $longitude, $distance));
            RestaurantRepository::addNearbyQueryClause($queryBuilder, $latitude, $longitude, $distance);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'coordinate' => [
                'property' => 'coordinate',
                'type' => 'string',
                'required' => false,
                'swagger' => [
                    'description' => 'Filter nearby restaurants by latitude, longitude. Example: 48.853286,2.369116',
                    'name' => 'coordinate',
                    'type' => 'string',
                ],
            ],
            'distance' => [
                'property' => 'distance',
                'type' => 'string',
                'required' => false,
                'swagger' => [
                    'description' => 'Specify distance for nearby restaurants, in meters. Default: 3000.',
                    'name' => 'distance',
                    'type' => 'integer',
                ],
            ]
        ];
    }
}
