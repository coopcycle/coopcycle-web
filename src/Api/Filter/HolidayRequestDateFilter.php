<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use AppBundle\Entity\HolidayRequest;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters holiday requests overlapping a date range, i.e ?date[after]=2026-06-29&date[before]=2026-07-05
 */
final class HolidayRequestDateFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== HolidayRequest::class) {
            return;
        }

        // otherwise filter is applied to order and page as well
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        if (!is_array($value)) {
            return;
        }

        if (isset($value['after'])) {
            $parameterName = $queryNameGenerator->generateParameterName('after');
            $queryBuilder
                ->andWhere(sprintf('o.endDate >= :%s', $parameterName))
                ->setParameter($parameterName, $value['after']);
        }

        if (isset($value['before'])) {
            $parameterName = $queryNameGenerator->generateParameterName('before');
            $queryBuilder
                ->andWhere(sprintf('o.startDate <= :%s', $parameterName))
                ->setParameter($parameterName, $value['before']);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $strategy) {
            $description[sprintf('%s[after]', $property)] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
            $description[sprintf('%s[before]', $property)] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
        }

        return $description;
    }
}
