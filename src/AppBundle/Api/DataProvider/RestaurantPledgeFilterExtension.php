<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use AppBundle\Entity\Restaurant;
use Doctrine\ORM\QueryBuilder;

/**
 * Custom collection extension to filter out restaurant suggestions.
 */
class RestaurantPledgeFilterExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null)
    {
        if (Restaurant::class !== $resourceClass) {
            return;
        }

        if ($operationName !== 'get') {
            return;
        }

        $parameterName = $queryNameGenerator->generateParameterName('state');

        $queryBuilder
            ->andWhere(sprintf('o.%s != :%s', 'state', $parameterName))
            ->setParameter($parameterName, Restaurant::STATE_PLEDGE);
    }
}
