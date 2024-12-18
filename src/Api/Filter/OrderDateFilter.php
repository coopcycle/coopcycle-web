<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Order\Model\OrderInterface;

final class OrderDateFilter extends AbstractContextAwareFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        // Do not use isPropertyMapped(), because this is a "virtual" property
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        $rangeStart = null;
        $rangeEnd = null;

        if (\is_array($value)) {
            $rangeStart = new \DateTime($value[DateFilterInterface::PARAMETER_AFTER]);
            $rangeEnd = new \DateTime($value[DateFilterInterface::PARAMETER_BEFORE]);
        } else {
            $dateTime = new \DateTime($value);
            $rangeStart = $dateTime;
            $rangeEnd = $dateTime;
        }

        $queryBuilder
            ->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE')
            // FIXME Move this to another filter?
            ->andWhere('o.state != :state_cart')
            ->setParameter('range', sprintf('[%s, %s]', $rangeStart->format('Y-m-d 00:00:00'), $rangeEnd->format('Y-m-d 23:59:59')))
            ->setParameter('state_cart', OrderInterface::STATE_CART);
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
