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

        $afterParameterName = $queryNameGenerator->generateParameterName('doneAfter');
        $beforeParameterName = $queryNameGenerator->generateParameterName('doneBefore');

        $queryBuilder
            ->andWhere(sprintf(':%s >= DATE(o.%s)', $afterParameterName, 'doneAfter'))
            ->andWhere(sprintf(':%s <= DATE(o.%s)', $beforeParameterName, 'doneBefore'))
            ->setParameter($afterParameterName, $value)
            ->setParameter($beforeParameterName, $value);
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
