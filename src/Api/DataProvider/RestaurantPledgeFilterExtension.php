<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use AppBundle\Entity\LocalBusiness;
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
        Operation $operation = null,
        array $context = []): void
    {
        if (LocalBusiness::class !== $resourceClass) {
            return;
        }

        if (!$operation instanceof GetCollection) {
            return;
        }

        $parameterName = $queryNameGenerator->generateParameterName('state');

        $queryBuilder
            ->andWhere(sprintf('o.%s != :%s', 'state', $parameterName))
            ->setParameter($parameterName, LocalBusiness::STATE_PLEDGE);
    }
}
