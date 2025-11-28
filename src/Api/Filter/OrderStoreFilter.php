<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Join;

final class OrderStoreFilter implements FilterInterface
{
    private string $storeIdProperty = 'delivery.store.id';

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Order::class) {
            return;
        }

        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        // The parameter may not be present
        if ($value instanceof ParameterNotFound || null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        // Add joins for nested property delivery.store.id
        [$joinAlias, $field] = $this->addJoinsForNestedProperty($this->storeIdProperty, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);

        $valueParameter = $queryNameGenerator->generateParameterName($field);

        if (is_array($value)) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->in(sprintf('%s.%s', $joinAlias, $field), sprintf(':%s', $valueParameter)))
                ->setParameter($valueParameter, $value);

            return;
        }

        $queryBuilder
            ->andWhere(\sprintf('%s.%s = :%s', $joinAlias, $field, $valueParameter))
            ->setParameter($valueParameter, $value);
    }

    /**
     * Helper method to add joins for nested properties (copied from AbstractFilter for compatibility)
     */
    private function addJoinsForNestedProperty(string $property, string $rootAlias, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $joinType): array
    {
        $propertyParts = explode('.', $property);
        $parentAlias = $rootAlias;
        $alias = null;

        while (count($propertyParts) > 1) {
            $part = array_shift($propertyParts);
            $alias = $queryNameGenerator->generateJoinAlias($part);

            $parts = $queryBuilder->getDQLPart('join');
            $joinExists = false;

            if (isset($parts[$parentAlias])) {
                foreach ($parts[$parentAlias] as $join) {
                    if ($join->getJoin() === sprintf('%s.%s', $parentAlias, $part)) {
                        $alias = $join->getAlias();
                        $joinExists = true;
                        break;
                    }
                }
            }

            if (!$joinExists) {
                if ($joinType === Join::INNER_JOIN) {
                    $queryBuilder->innerJoin(sprintf('%s.%s', $parentAlias, $part), $alias);
                } else {
                    $queryBuilder->leftJoin(sprintf('%s.%s', $parentAlias, $part), $alias);
                }
            }

            $parentAlias = $alias;
        }

        return [$parentAlias, $propertyParts[0]];
    }

    public function getDescription(string $resourceClass): array
    {
        // For BC, this function is not useful anymore when documentation occurs on the Parameter
        return [];
    }
}
