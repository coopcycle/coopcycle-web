<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;

/**
 * Filters orders by "organization", i.e. either a Store (on-demand delivery client)
 * or a restaurant/LocalBusiness (food order client).
 *
 * Values are expected to be the IRI of the organization (e.g. "/api/stores/1" or
 * "/api/restaurants/1"), as returned by the invoice_line_items endpoints. Bare
 * numeric values are also accepted for backwards-compatibility and are treated
 * as store ids.
 */
final class OrderOrganizationFilter extends AbstractFilter
{
    private string $organizationIdAlias = 'organization';

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Order::class) {
            return;
        }

        // expose alias in the API instead of a path to a nested property
        if ($this->organizationIdAlias !== $property) {
            return;
        }

        [$storeIds, $restaurantIds] = $this->splitOrganizationIds(is_array($value) ? $value : [$value]);

        if (0 === count($storeIds) && 0 === count($restaurantIds)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $conditions = [];

        if (count($storeIds) > 0) {
            $deliveryAlias = $queryNameGenerator->generateJoinAlias('delivery');
            $storeAlias = $queryNameGenerator->generateJoinAlias('store');
            $queryBuilder
                ->leftJoin(sprintf('%s.delivery', $rootAlias), $deliveryAlias)
                ->leftJoin(sprintf('%s.store', $deliveryAlias), $storeAlias);

            $storeParameter = $queryNameGenerator->generateParameterName('storeIds');
            $conditions[] = sprintf('%s.id IN (:%s)', $storeAlias, $storeParameter);
            $queryBuilder->setParameter($storeParameter, $storeIds);
        }

        if (count($restaurantIds) > 0) {
            $vendorAlias = $queryNameGenerator->generateJoinAlias('vendor');
            $restaurantAlias = $queryNameGenerator->generateJoinAlias('restaurant');
            $queryBuilder
                ->leftJoin(sprintf('%s.vendors', $rootAlias), $vendorAlias)
                ->leftJoin(sprintf('%s.restaurant', $vendorAlias), $restaurantAlias);

            $restaurantParameter = $queryNameGenerator->generateParameterName('restaurantIds');
            $conditions[] = sprintf('%s.id IN (:%s)', $restaurantAlias, $restaurantParameter);
            $queryBuilder->setParameter($restaurantParameter, $restaurantIds);
        }

        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$conditions));
    }

    /**
     * @param array $values
     * @return array{0: int[], 1: int[]}
     */
    private function splitOrganizationIds(array $values): array
    {
        $storeIds = [];
        $restaurantIds = [];

        foreach ($values as $value) {
            if (is_string($value) && str_starts_with($value, '/api/restaurants/')) {
                $restaurantIds[] = (int) substr($value, strlen('/api/restaurants/'));
            } elseif (is_string($value) && str_starts_with($value, '/api/stores/')) {
                $storeIds[] = (int) substr($value, strlen('/api/stores/'));
            } elseif (is_numeric($value)) {
                // Backwards-compatibility: bare numeric ids are store ids
                $storeIds[] = (int) $value;
            }
        }

        return [$storeIds, $restaurantIds];
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'organization' => [
                'property' => $this->organizationIdAlias,
                'type' => Type::BUILTIN_TYPE_STRING,
                'required' => false,
                'is_collection' => false,
            ],
            'organization[]'=> [
                'property' => $this->organizationIdAlias,
                'type' => Type::BUILTIN_TYPE_STRING,
                'required' => false,
                'is_collection' => true,
            ]
        ];
    }
}
