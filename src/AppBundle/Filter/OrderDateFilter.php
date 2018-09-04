<?php

namespace AppBundle\Filter;

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

        $queryBuilder
            ->andWhere('DATE(o.shippedAt) = :date')
            // FIXME Move this to another filter?
            ->andWhere('o.state != :state_cart')
            ->setParameter('date', $value)
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
