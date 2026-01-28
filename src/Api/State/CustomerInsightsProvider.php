<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Dto\CustomerInsightsDto;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Sylius\Customer\CustomerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;

final class CustomerInsightsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var CustomerInterface */
        $customer = $this->provider->provide($operation, $uriVariables, $context);

        // Average order total
        $qb = $this->createBaseQueryBuilder($customer)
            ->select('COALESCE(AVG(o.total), 0)');

        $averageOrderTotal = $qb->getQuery()->getSingleScalarResult();

        // First order
        $qb = $this->createBaseQueryBuilder($customer)
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults(1);

        $firstOrder = $qb->getQuery()->getOneOrNullResult();

        // Last order
        $qb = $this->createBaseQueryBuilder($customer)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(1);

        $lastOrder = $qb->getQuery()->getOneOrNullResult();

        // Number of orders
        $qb = $this->createBaseQueryBuilder($customer)
            ->select('COUNT(o.id)');

        $numberOfOrders = $qb->getQuery()->getSingleScalarResult();

        // // Orders during intervals
        // $intervals = [
        //     '1 month',
        //     '3 months',
        //     '6 months',
        // ];
        // $ordersByInterval = [];

        // $qb = $this->createBaseQueryBuilder($customer);
        // $qb
        //     ->select('COUNT(o.id)')
        //     ->andWhere($qb->expr()->between('o.createdAt', 'date_subtract(:now, :interval)', ':now'))
        //     ->setParameter('now', new \DateTime())
        //     ;

        // foreach ($intervals as $interval) {
        //     $qb->setParameter('interval', $interval);
        //     $ordersByInterval[$interval] = $qb->getQuery()->getSingleScalarResult();
        // }

        // Favorite restaurant

        $favoriteRestaurant = null;

        $qb = $this->createBaseQueryBuilder($customer)
            ->select('r.id', 'r.name', 'COUNT(o.id) AS number_of_orders')
            ->leftJoin(OrderVendor::class, 'v', Expr\Join::WITH, 'v.order = o.id')
            ->leftJoin(LocalBusiness::class, 'r', Expr\Join::WITH, 'v.restaurant = r.id')
            ->groupBy('r.id')
            ->orderBy('number_of_orders', 'DESC')
            ->setMaxResults(1)
            ;

        $favResult = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);
        if ($favResult) {
            $favoriteRestaurant = $this->entityManager->getRepository(LocalBusiness::class)->find($favResult['id']);
        }

        // ---

        $insights = new CustomerInsightsDto();

        $insights->averageOrderTotal = $averageOrderTotal;
        $insights->firstOrderedAt = $firstOrder?->getCreatedAt();
        $insights->lastOrderedAt = $lastOrder?->getCreatedAt();
        $insights->numberOfOrders = $numberOfOrders;
        $insights->favoriteRestaurant = $favoriteRestaurant;

        return $insights;
    }

    private function createBaseQueryBuilder(CustomerInterface $customer): QueryBuilder
    {
        return $this->entityManager->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.state = :fulfilled')
            ->setParameter('customer', $customer)
            ->setParameter('fulfilled', Order::STATE_FULFILLED);
    }
}
