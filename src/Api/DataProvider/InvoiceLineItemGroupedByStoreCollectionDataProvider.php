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
        $qb = $this->entityManager->getRepository(Order::class)->createQueryBuilder('o');
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
                $orders = $extension->getResult($qb, $resourceClass, $operationName, $context);
                return $this->groupByStore($orders);
            }
        }

        $orders = $qb->getQuery()->getResult();
        return $this->groupByStore($orders);
    }

    private function groupByStore($orders)
    {
        $ordersByStore = [];
        foreach ($orders as $result) {
            $storeId = $result->getDelivery()->getStore()->getId();
            if (!isset($ordersByStore[$storeId])) {
                $ordersByStore[$storeId] = [];
            }
            $ordersByStore[$storeId][] = $result;
        }

        $activityByStore = [];

        foreach ($ordersByStore as $storeId => $orders) {
            $store = $orders[0]->getDelivery()->getStore();
            $total = array_reduce($orders, function ($carry, $order) {
                return $carry + $order->getTotal();
            }, 0);
            $taxTotal = array_reduce($orders, function ($carry, $order) {
                return $carry + $order->getTaxTotal();
            }, 0);
            $subTotal = $total - $taxTotal;

            $activityByStore[] = [
                'store' => $store,
                'orders' => count($orders),
                'subTotal' => $subTotal,
                'taxTotal' => $taxTotal,
                'total' => $total,
            ];
        }

        return $activityByStore;
    }
}
