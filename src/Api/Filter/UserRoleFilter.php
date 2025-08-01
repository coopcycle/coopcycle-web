<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class UserRoleFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = [])
    {
        // otherwise filter is applied to order and page as well
        if (
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        $queryNameGenerator->generateParameterName($property);

        $roles = [];
        if (!is_array($value)) {
            $roles[] = $value;
        } else {
            $roles = $value;
        }

        if (count($roles) > 0) {
            $rolesClause = $queryBuilder->expr()->orX();
            foreach ($roles as $role) {
                $fieldName = sprintf('o.%s', $property);
                $fieldValue = $queryBuilder->expr()->literal('%' . $role . '%');

                $rolesClause->add($queryBuilder->expr()->like($fieldName, $fieldValue));
            }
            $queryBuilder->andWhere($rolesClause);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $strategy) {
            $description[$property] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
        }

        return $description;
    }
}
