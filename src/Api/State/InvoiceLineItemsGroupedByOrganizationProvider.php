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
        $qb = $this->entityManager->getRepository(Order::class)->createOptimizedQueryBuilder('o')
            // OrderVendor has a composite identifier, EntityPreloader can't preload it;
            // eager-load it via the query itself instead
            ->addSelect('v', 'vr')
            ->leftJoin('o.vendors', 'v')
            ->leftJoin('v.restaurant', 'vr');

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
                $ordersGrouppedByOrganization = $this->groupByOrganization($orders);

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

                return new ArrayPaginator($ordersGrouppedByOrganization, $offset, $itemsPerPage);
            }
        }

        $orders = $this->getResultWithPreloadedEntities($qb);

        return $this->groupByOrganization($orders);
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

    private function groupByOrganization($orders)
    {
        $ordersByOrganization = [];
        foreach ($orders as $order) {
            $store = $order->getDelivery()?->getStore();
            $restaurant = $store ? null : $order->getRestaurant();

            if ($store) {
                $organizationId = sprintf('store-%d', $store->getId());
            } elseif ($restaurant) {
                $organizationId = sprintf('restaurant-%d', $restaurant->getId());
            } else {
                //FIXME; currently only orders linked to a Store or a restaurant are supported
                continue;
            }

            if (!isset($ordersByOrganization[$organizationId])) {
                $ordersByOrganization[$organizationId] = [];
            }
            $ordersByOrganization[$organizationId][] = $order;
        }

        $activityByOrganization = [];

        foreach ($ordersByOrganization as $organizationId => $orders) {
            $store = $orders[0]->getDelivery()?->getStore();
            $organization = $store ?? $orders[0]->getRestaurant();

            $total = array_reduce($orders, function ($carry, $order) {
                return $carry + $order->getTotal();
            }, 0);
            $tax = array_reduce($orders, function ($carry, $order) {
                return $carry + $order->getTaxTotal();
            }, 0);
            $subTotal = $total - $tax;

            $activityByOrganization[] = new InvoiceLineItemGroupedByOrganization(
                $organizationId,
                $organization->getLegalName() ?? $organization->getName(),
                $organization->getName(),
                count($orders),
                $subTotal,
                $tax,
                $total
            );
        }

        usort($activityByOrganization, function ($a, $b) {
            return strcmp($a->organizationLegalName, $b->organizationLegalName);
        });

        return $activityByOrganization;
    }
}
