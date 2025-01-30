<?php

namespace AppBundle\Api\Filter;

use AppBundle\Entity\Task;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

final class TaskOrderFilter extends AbstractContextAwareFilter
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

        $parameterName = $queryNameGenerator->generateParameterName($property); // Generate a unique parameter name to avoid collisions with other filters
        $fieldName = $queryNameGenerator->generateParameterName($property); // Generate a unique parameter name to avoid collisions with other filters

        $queryBuilder
            ->addSelect(sprintf('CASE WHEN o.type = :%s THEN 1 ELSE 0 END AS HIDDEN %s', $parameterName,$fieldName))
            ->orderBy('o.doneBefore', 'ASC')
            ->addOrderBy($fieldName, 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->setParameter($parameterName, Task::TYPE_DROPOFF);
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
