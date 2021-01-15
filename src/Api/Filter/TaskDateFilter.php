<?php

namespace AppBundle\Api\Filter;

use AppBundle\Entity\Task;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;

final class TaskDateFilter extends AbstractContextAwareFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
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
