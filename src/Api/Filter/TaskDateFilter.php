<?php

namespace AppBundle\Api\Filter;

use AppBundle\Entity\Task;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;

final class TaskDateFilter implements FilterInterface
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
        $afterParameterName = $queryNameGenerator->generateParameterName('doneAfter');
        $beforeParameterName = $queryNameGenerator->generateParameterName('doneBefore');

        $queryBuilder
            ->andWhere(sprintf(':%s >= DATE(%s.%s)', $afterParameterName, $alias, 'doneAfter'))
            ->andWhere(sprintf(':%s <= DATE(%s.%s)', $beforeParameterName, $alias, 'doneBefore'))
            ->setParameter($afterParameterName, $value)
            ->setParameter($beforeParameterName, $value);
    }

    public function getDescription(string $resourceClass): array
    {
        // For BC, this function is not useful anymore when documentation occurs on the Parameter
        return [];
    }
}
