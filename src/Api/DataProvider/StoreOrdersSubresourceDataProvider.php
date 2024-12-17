<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Store;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\DataProvider\SubresourceDataProviderInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr\Join;

final class StoreOrdersSubresourceDataProvider implements SubresourceDataProviderInterface, RestrictedDataProviderInterface
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Order::class === $resourceClass && $operationName === 'api_stores_orders_get_subresource';
    }

    /**
     * @throws ItemNotFoundException
     */
    public function getSubresource(string $resourceClass, array $identifiers, array $context, string $operationName = null)
    {
        [$identifierResourceClass, $identifier] = current($context['identifiers']);

        $id = (int)$context['subresource_identifiers'][$identifier];

        $store = $this->doctrine->getRepository(Store::class)->find($id);
        if (!$store) {
            throw new ItemNotFoundException();
        }

        $qb = $this->doctrine->getRepository(Order::class)->createQueryBuilder('o');

        $qb->join(Delivery::class, 'd', Join::WITH, 'd.order = o');

        $qb
            ->where('d.store = :store')
            ->setParameter('store', $store);

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection(
                $qb,
                $queryNameGenerator,
                $resourceClass,
                $operationName,
                $context
            );

            if (
                $extension instanceof QueryResultCollectionExtensionInterface
                &&
                $extension->supportsResult($resourceClass, $operationName, $context)
            ) {
                return $extension->getResult($qb, $resourceClass, $operationName, $context);
            }
        }

        return $qb->getQuery()->getResult();
    }
}
