<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\State\Pagination\ArrayPaginator;
use AppBundle\Api\Dto\InvoiceLineItemGroupedByOrganization;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class InvoiceLineItemsGroupedByOrganizationProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();
        $qb = $this->entityManager->getRepository(Order::class)->createOptimizedQueryBuilder('o');

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $isPaginationExtension = $extension instanceof QueryResultCollectionExtensionInterface
                &&
                $extension->supportsResult($resourceClass, $operation, $context);

            // Do not apply pagination extension directly, as it will conflict with the groupBy
            if (!$isPaginationExtension) {
                $extension->applyToCollection(
                    $qb,
                    $queryNameGenerator,
                    $resourceClass,
                    $operation,
                    $context
                );
            } else {
                // Fetch all orders first, and then apply the pagination extension
                $orders = $this->getResultWithPreloadedEntities($qb);
                $ordersGrouppedByStore = $this->groupByStore($orders);

                // Relying on API Platform's pagination extension to get the pagination parameters (offset and page size)
                $extension->applyToCollection(
                    $qb,
                    $queryNameGenerator,
                    $resourceClass,
                    $operation,
                    $context
                );
                $extension->getResult($qb, $resourceClass, $operation, $context);

                $offset = $qb->getFirstResult();
                $itemsPerPage = $qb->getMaxResults();
                
                return new ArrayPaginator($ordersGrouppedByStore, $offset, $itemsPerPage);
            }
        }

        $orders = $this->getResultWithPreloadedEntities($qb);

        return $this->groupByStore($orders);
    }

    private function getResultWithPreloadedEntities(QueryBuilder $qb): array
    {
        $orders = $qb->getQuery()->getResult();

        //Optimization: to avoid extra queries preload one-to-many relations that will be used later
        $preloader = new EntityPreloader($this->entityManager);

        $orderItems = $preloader->preload($orders, 'items');
        $preloader->preload($orders, 'adjustments');

        $preloader->preload($orderItems, 'adjustments');

        $delivery = $preloader->preload($orders, 'delivery');
        $preloader->preload($delivery, 'store');

        return $orders;
    }

    private function groupByStore($orders)
    {
        $ordersByStore = [];
        foreach ($orders as $order) {
            $storeId = $order->getDelivery()?->getStore()?->getId();

            //FIXME; currently only On Demand Delivery orders for stores are supported
            if (null === $storeId) {
                continue;
            }

            if (!isset($ordersByStore[$storeId])) {
                $ordersByStore[$storeId] = [];
            }
            $ordersByStore[$storeId][] = $order;
        }

        $activityByStore = [];

        foreach ($ordersByStore as $orders) {
            $store = $orders[0]->getDelivery()->getStore();
            $total = array_reduce($orders, function ($carry, $order) {
                return $carry + $order->getTotal();
            }, 0);
            $tax = array_reduce($orders, function ($carry, $order) {
                return $carry + $order->getTaxTotal();
            }, 0);
            $subTotal = $total - $tax;

            $activityByStore[] = new InvoiceLineItemGroupedByOrganization(
                $store->getId(),
                $store->getLegalName() ?? $store->getName(),
                count($orders),
                $subTotal,
                $tax,
                $total
            );
        }

        usort($activityByStore, function ($a, $b) {
            return strcmp($a->organizationLegalName, $b->organizationLegalName);
        });

        return $activityByStore;
    }
}
