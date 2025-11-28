<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Order\Model\OrderInterface;

final class OrderDateFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        // The parameter may not be present
        if ($value instanceof ParameterNotFound || null === $value) {
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

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->andWhere(sprintf('OVERLAPS(%s.shippingTimeRange, CAST(:range AS tsrange)) = TRUE', $alias))
            // FIXME Move this to another filter?
            ->andWhere(sprintf('%s.state != :state_cart', $alias))
            ->setParameter('range', sprintf('[%s, %s]', $rangeStart->format('Y-m-d 00:00:00'), $rangeEnd->format('Y-m-d 23:59:59')))
            ->setParameter('state_cart', OrderInterface::STATE_CART);
    }

    public function getDescription(string $resourceClass): array
    {
        // For BC, this function is not useful anymore when documentation occurs on the Parameter
        return [];
    }
}
