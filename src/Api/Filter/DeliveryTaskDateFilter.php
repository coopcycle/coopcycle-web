<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;

class DeliveryTaskDateFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        // The parameter may not be present
        if ($value instanceof ParameterNotFound || null === $value) {
            return;
        }

        $property = $parameter->getKey();

        if (!in_array($property, ['pickup.before', 'pickup.after', 'dropoff.before', 'dropoff.after'])) {
            return;
        }

        [$taskType, $dateType] = explode('.', $property);

        if (!is_array($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $itemAlias = $queryNameGenerator->generateJoinAlias('item');
        $taskAlias = $queryNameGenerator->generateJoinAlias('task');

        $queryBuilder
            ->join(sprintf('%s.items', $alias), $itemAlias)
            ->join(sprintf('%s.task', $itemAlias), $taskAlias)
            ->andWhere(sprintf('%s.type = :task_type_%s', $taskAlias, $taskType))
            ->setParameter(sprintf('task_type_%s', $taskType), strtoupper($taskType));

        foreach ($value as $operator => $operatorValue) {
            $parameterName = $queryNameGenerator->generateParameterName($property);

            switch ($operator) {
                case 'before':
                    $queryBuilder
                        ->andWhere(sprintf('%s.done%s <= :%s', $taskAlias, ucfirst($dateType), $parameterName))
                        ->setParameter($parameterName, $operatorValue);
                    break;
                case 'after':
                    $queryBuilder
                        ->andWhere(sprintf('%s.done%s >= :%s', $taskAlias, ucfirst($dateType), $parameterName))
                        ->setParameter($parameterName, $operatorValue);
                    break;
                case 'strictly_before':
                    $queryBuilder
                        ->andWhere(sprintf('%s.done%s < :%s', $taskAlias, ucfirst($dateType), $parameterName))
                        ->setParameter($parameterName, $operatorValue);
                    break;
                case 'strictly_after':
                    $queryBuilder
                        ->andWhere(sprintf('%s.done%s > :%s', $taskAlias, ucfirst($dateType), $parameterName))
                        ->setParameter($parameterName, $operatorValue);
                    break;
            }
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'pickup.before' => [
                'property' => 'pickup.before',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by pickup task done before date',
            ],
            'pickup.after' => [
                'property' => 'pickup.after',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by pickup task done after date',
            ],
            'dropoff.before' => [
                'property' => 'dropoff.before',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by dropoff task done before date',
            ],
            'dropoff.after' => [
                'property' => 'dropoff.after',
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by dropoff task done after date',
            ],
        ];
    }
}
