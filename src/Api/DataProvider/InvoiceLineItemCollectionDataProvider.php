<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

final class InvoiceLineItemCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Order::class === $resourceClass && 'invoice_line_items' === $operationName;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $qb = $this->doctrine->getRepository(Order::class)->createQueryBuilder('o');

        $qb->join(Delivery::class, 'd', Join::WITH, 'd.order = o');

//        $qb
//            ->where('d.store = :store')
//            ->setParameter('store', $store);

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
