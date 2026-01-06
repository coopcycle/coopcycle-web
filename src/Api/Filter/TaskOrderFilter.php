<?php

namespace AppBundle\Api\Filter;

use AppBundle\Entity\Task;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;

final class TaskOrderFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        // Only works on Task class
        if ($resourceClass !== Task::class) {
            return;
        }

        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        // The parameter may not be present
        if ($value instanceof ParameterNotFound || null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $property = $parameter->getProperty() ?? $parameter->getKey() ?? 'order';

        $parameterName = $queryNameGenerator->generateParameterName($property);
        $fieldName = $queryNameGenerator->generateParameterName($property);

        $queryBuilder
            ->addSelect(sprintf('CASE WHEN %s.type = :%s THEN 1 ELSE 0 END AS HIDDEN %s', $alias, $parameterName, $fieldName))
            ->orderBy(sprintf('%s.doneBefore', $alias), 'ASC')
            ->addOrderBy($fieldName, 'ASC')
            ->addOrderBy(sprintf('%s.id', $alias), 'ASC')
            ->setParameter($parameterName, Task::TYPE_DROPOFF);
    }

    public function getDescription(string $resourceClass): array
    {
        // For BC, this function is not useful anymore when documentation occurs on the Parameter
        return [];
    }
}
