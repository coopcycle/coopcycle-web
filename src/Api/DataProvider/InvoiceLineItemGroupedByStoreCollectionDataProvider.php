<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

final class InvoiceLineItemGroupedByStoreCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Order::class === $resourceClass && 'invoice_line_items_grouped_by_store' === $operationName;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select([
                'IDENTITY(d.store) AS store_id',
                'COUNT(o.id) as orders',
                'SUM(o.total) as total',
            ])
            ->from(Order::class, 'o');

        $qb->join(Delivery::class, 'd', Join::WITH, 'd.order = o');

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
                // Reset the default orderBy, as it conflicts with the groupBy
                $qb->resetDQLParts(['orderBy']);

                return $extension->getResult($this->groupByStore($qb), $resourceClass, $operationName, $context);
            }
        }

        return $this->groupByStore($qb)
            ->getQuery()->getResult();
    }

    private function groupByStore($qb)
    {
        return $qb
            ->groupBy('d.store');
    }
}
