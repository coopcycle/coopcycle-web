<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use AppBundle\Api\Dto\InvoiceLineItemGroupedByOrganization;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

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
        $qb->join(Delivery::class, 'd', Join::WITH, 'd.order = o');

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $isPaginationExtension = $extension instanceof QueryResultCollectionExtensionInterface
                &&
                $extension->supportsResult($resourceClass, $operationName, $context);

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
                $orders = $qb->getQuery()->getResult();

                // Relying on API Platform's pagination extension to get the pagination parameters (offset and page size)

                // Reset the default orderBy, as it conflicts with the groupBy
                $qb->resetDQLParts(['orderBy']);
                $qb->select('IDENTITY(d.store) AS store_id');
                $qb->groupBy('d.store');

                $extension->applyToCollection(
                    $qb,
                    $queryNameGenerator,
                    $resourceClass,
                    $operationName,
                    $context
                );
                $extension->getResult($qb, $resourceClass, $operationName, $context);
                $offset = $qb->getFirstResult();
                $pageSize = $qb->getMaxResults();

                // Manually applying pagination parameters to the array
                return array_slice(
                    $this->groupByStore($orders),
                    $offset,
                    $pageSize
                );
            }
        }

        $orders = $qb->getQuery()->getResult();
        return $this->groupByStore($orders);
    }

    private function groupByStore($orders)
    {
        $ordersByStore = [];
        foreach ($orders as $order) {
            $storeId = $order->getDelivery()->getStore()->getId();
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
