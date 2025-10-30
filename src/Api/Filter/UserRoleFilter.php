<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;

final class UserRoleFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        // The parameter may not be present
        if ($value instanceof ParameterNotFound || null === $value) {
            return;
        }

        $property = $parameter->getProperty() ?? $parameter->getKey() ?? 'roles';
        $alias = $queryBuilder->getRootAliases()[0];

        $roles = [];
        if (!is_array($value)) {
            $roles[] = $value;
        } else {
            $roles = $value;
        }

        if (count($roles) > 0) {
            $rolesClause = $queryBuilder->expr()->orX();
            foreach ($roles as $role) {
                $fieldName = sprintf('%s.%s', $alias, $property);
                $fieldValue = $queryBuilder->expr()->literal('%' . $role . '%');

                $rolesClause->add($queryBuilder->expr()->like($fieldName, $fieldValue));
            }
            $queryBuilder->andWhere($rolesClause);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        // For BC, this function is not useful anymore when documentation occurs on the Parameter
        return [];
    }
}
