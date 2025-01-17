<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class InvoiceLineItemCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Order::class === $resourceClass && (
                'invoice_line_items' === $operationName
                || 'invoice_line_items_export' === $operationName
                || 'invoice_line_items_odoo_export' === $operationName
            );
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $qb = $this->entityManager->getRepository(Order::class)->createOpmizedQueryBuilder('o')
            // Additional optimization: preload vendors
            ->addSelect('v')
            ->leftJoin('o.vendors', 'v');

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
                $extension->supportsResult($resourceClass, $operationName, $context) // @phpstan-ignore arguments.count
            ) {
                return $this->resultWithPreloadedEntities(
                    $extension->getResult($qb, $resourceClass, $operationName, $context) // @phpstan-ignore arguments.count
                );
            }
        }

        return $this->resultWithPreloadedEntities($qb->getQuery()->getResult());
    }

    private function resultWithPreloadedEntities(iterable $data): iterable
    {
        $orders = iterator_to_array($data);

        //Optimization: to avoid extra queries preload one-to-many relations that will be used later
        $preloader = new EntityPreloader($this->entityManager);

        $orderItems = $preloader->preload($orders, 'items');
        $preloader->preload($orders, 'adjustments');

        $preloader->preload($orderItems, 'adjustments');
        $productVariants = $preloader->preload($orderItems, 'variant');

        $delivery = $preloader->preload($orders, 'delivery');
        $preloader->preload($delivery, 'store');

        return $data;
    }
}
