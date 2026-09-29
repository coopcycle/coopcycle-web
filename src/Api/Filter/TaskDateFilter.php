<?php

namespace AppBundle\Api\Filter;

use AppBundle\Entity\Task;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class TaskDateFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        // Only works on Task class
        if ($resourceClass !== Task::class) {
            return;
        }

        // otherwise filter is applied to order and page as well
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        try {
            $startOfDay = new \DateTime(sprintf('%s 00:00:00', $value));
        } catch (\Exception $e) {
            return;
        }

        $startOfNextDay = (clone $startOfDay)->modify('+1 day');

        $afterParameterName = $queryNameGenerator->generateParameterName('doneAfter');
        $beforeParameterName = $queryNameGenerator->generateParameterName('doneBefore');

        // Match the tasks whose [doneAfter, doneBefore] window covers the requested day.
        // Expressed as a half-open range on the raw columns instead of DATE(o.doneAfter)/
        // DATE(o.doneBefore): wrapping the columns in a function makes the predicate
        // non-sargable, so Postgres cannot use an index and scans the whole task table.
        $queryBuilder
            ->andWhere(sprintf('o.%s < :%s', 'doneAfter', $afterParameterName))
            ->andWhere(sprintf('o.%s >= :%s', 'doneBefore', $beforeParameterName))
            ->setParameter($afterParameterName, $startOfNextDay)
            ->setParameter($beforeParameterName, $startOfDay);
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
