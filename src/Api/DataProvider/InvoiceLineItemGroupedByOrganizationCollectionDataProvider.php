<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\ArrayPaginator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Api\Dto\InvoiceLineItemGroupedByOrganization;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class InvoiceLineItemGroupedByOrganizationCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Order::class === $resourceClass && 'invoice_line_items_grouped_by_organization' === $operationName;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        $qb = $this->entityManager->getRepository(Order::class)->createQueryBuilder('o');
        //Optimization: to avoid extra queries during Hydration preload bi-directional one-to-one relations
        // https://stackoverflow.com/questions/12362901/doctrine2-one-to-one-relation-auto-loads-on-query/34353840#34353840
        // https://github.com/doctrine/orm/issues/4389
        $qb->addSelect([
            'order_delivery',
            'order_timeline',
            'order_invitation',
            'order_loopeat_credentials',
            'order_loopeat_details'
        ])
            ->leftJoin('o.delivery', 'order_delivery')
            ->leftJoin('o.timeline', 'order_timeline')
            ->leftJoin('o.invitation', 'order_invitation')
            ->leftJoin('o.loopeatCredentials', 'order_loopeat_credentials')
            ->leftJoin('o.loopeatDetails', 'order_loopeat_details');

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $isPaginationExtension = $extension instanceof QueryResultCollectionExtensionInterface
                &&
                $extension->supportsResult($resourceClass, $operationName, $context); // @phpstan-ignore arguments.count

            // Do not apply pagination extension directly, as it will conflict with the groupBy
            if (!$isPaginationExtension) {
                $extension->applyToCollection(
                    $qb,
                    $queryNameGenerator,
                    $resourceClass,
                    $operationName,
                    $context
                );
            } else {
                // Fetch all orders first, and then apply the pagination extension
                $orders = $this->getResultWithPreloadedEntities($qb);
                $ordersGrouppedByStore = $this->groupByStore($orders);

                // Relying on API Platform's pagination extension to get the pagination parameters (offset and page size)
                // @phpstan-ignore arguments.count
                $extension->applyToCollection(
                    $qb,
                    $queryNameGenerator,
                    $resourceClass,
                    $operationName,
                    $context
                );
                $extension->getResult($qb, $resourceClass, $operationName, $context); // @phpstan-ignore arguments.count

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

        foreach ($ordersByStore as $storeId => $orders) {
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
