<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryRepository;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Refund;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderVendor;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use DateTime;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class OrderRepository extends BaseOrderRepository
{

    private ?DeliveryRepository $deliveryRepository;

    // This method is called by Dependency Injection
    public function setDeliveryRepository(?DeliveryRepository $deliveryRepository): void
    {
        $this->deliveryRepository = $deliveryRepository;
    }

    public function findCartsByRestaurant(LocalBusiness $restaurant)
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.state = :state')
            ->setParameter('state', OrderInterface::STATE_CART)
        ;

        $qb = self::addVendorClause($qb, 'o', $restaurant);

        return $qb->getQuery()->getResult();
    }

    public function findOrdersByDate(\DateTime $date)
    {
        $start = clone $date;
        $end   = clone $date;

        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);

        return $this->findOrdersByDateRange($start, $end, $withVendor = true);
    }

    public function findOrdersByRestaurantAndDateRange(LocalBusiness $restaurant,
        \DateTime $start,
        \DateTime $end,
        bool $includeNewMultiVendorOrders)
    {
        $qb = $this->createQueryBuilder('o');
        $qb = self::addVendorClause($qb, 'o', $restaurant);
        $qb = self::addShippingTimeRangeClause($qb, 'o', $start, $end);

        // We always remove the carts
        $qb->andWhere($qb->expr()->neq('o.state', ':state_cart'));
        $qb->setParameter('state_cart', OrderInterface::STATE_CART);

        // When there are multiple vendors,
        // we filter out the orders with state = new
        if (!$includeNewMultiVendorOrders) {

            // We build a subquery to find
            // the new orders with multiple vendors
            // https://stackoverflow.com/questions/6637506/doing-a-where-in-subquery-in-doctrine-2

            $newOrdersWithMoreThanOneVendor = $this->createQueryBuilder('o2');
            $newOrdersWithMoreThanOneVendor = self::addVendorClause($newOrdersWithMoreThanOneVendor, 'o2', $restaurant, 'o2v');
            $newOrdersWithMoreThanOneVendor->select('o2.id');
            $newOrdersWithMoreThanOneVendor->andWhere('o2.state = :state_new');
            $newOrdersWithMoreThanOneVendor->groupBy('o2.id');
            $newOrdersWithMoreThanOneVendor->having('COUNT(o2v.restaurant) > 1');
            $newOrdersWithMoreThanOneVendor->setParameter('state_new', OrderInterface::STATE_NEW);

            $qb->andWhere(
                $qb->expr()->notIn('o.id', $newOrdersWithMoreThanOneVendor->getQuery()->getDQL())
            );
            $qb->setParameter('state_new', OrderInterface::STATE_NEW);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOrdersByDateRange(\DateTime $start, \DateTime $end, bool $withVendor = false)
    {
        $qb = $this->createQueryBuilder('o');

        $this->addDateRangeClause($qb, $start, $end);

        if ($withVendor) {
            $qb->join(OrderVendor::class, 'v', Join::WITH, 'o.id = v.order');
        }

        $qb
            ->andWhere('o.state != :state_cart')
            ->setParameter('state_cart', OrderInterface::STATE_CART);

        return $qb->getQuery()->getResult();
    }

    public function findFulfilledOrdersByDateRange(\DateTime $start, \DateTime $end, $asQueryBuilder = false)
    {
        $qb = $this->createQueryBuilder('o');

        $this->addDateRangeClause($qb, $start, $end);

        $qb
            ->andWhere('o.state = :state_fulfilled')
            ->setParameter('state_fulfilled', OrderInterface::STATE_FULFILLED);

        if ($asQueryBuilder) {
            return $qb;
        }

        return $qb->getQuery()->getResult();
    }

    public function findFulfilledOrdersByDate(\DateTime $date)
    {
        $qb = $this->createQueryBuilder('o');

        $this->addDateClause($qb, $date);

        $qb
            ->andWhere('o.state = :state_fulfilled')
            ->setParameter('state_fulfilled', OrderInterface::STATE_FULFILLED);

        return $qb->getQuery()->getResult();
    }

    public function findByUser(UserInterface $user)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.state != :state_cart')
            ->andWhere('o.customer = :customer')
            ->setParameter('state_cart', OrderInterface::STATE_CART)
            ->setParameter('customer', $user->getCustomer())
            ->addOrderBy('o.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public static function addShippingTimeRangeClause(QueryBuilder $qb, $alias, \DateTime $start, \DateTime $end)
    {
        return $qb
            ->andWhere(sprintf('OVERLAPS(%s.shippingTimeRange, CAST(:range AS tsrange)) = TRUE', $alias))
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')))
            ;
    }

    private function addDateRangeClause(QueryBuilder $qb, \DateTime $start, \DateTime $end)
    {
        $qb
            ->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE')
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')))
            ;

        return $this;
    }

    private function addDateClause(QueryBuilder $qb, \DateTime $date)
    {
        $qb
            ->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE')
            ->setParameter('range', sprintf('[%s, %s]', $date->format('Y-m-d 00:00:00'), $date->format('Y-m-d 23:59:59')))
            ;

        return $this;
    }

    public function countByCustomerAndCoupon(CustomerInterface $customer, PromotionCouponInterface $coupon): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.promotionCoupon = :coupon')
            ->andWhere('o.state NOT IN (:states)')
            ->setParameter('customer', $customer)
            ->setParameter('coupon', $coupon)
            ->setParameter('states', [OrderInterface::STATE_CART, OrderInterface::STATE_CANCELLED])
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function findOneByTask(Task $task): ?OrderInterface
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->leftJoin(Delivery::class, 'd', Join::WITH, 'o.id = d.order')
            ->leftJoin(Task::class, 't', Join::WITH, 'd.id = t.delivery')
            ->andWhere('t.id = :task')
            ->setParameter('task', $task)
            ;

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function search($q): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o');

        $qb
            ->leftJoin(Customer::class, 'c', Join::WITH, 'o.customer = c.id')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->gt('SIMILARITY(o.number, :q)', 0),
                $qb->expr()->gt('SIMILARITY(c.email, :q)', 0)
            ))
            ->andWhere($qb->expr()->neq('o.state', ':state_cart'))
            ->addOrderBy('SIMILARITY(o.number, :q)', 'DESC')
            ->addOrderBy('SIMILARITY(c.email, :q)', 'DESC')
            ->addOrderBy('o.createdAt', 'DESC')
            ->setParameter('q', strtolower($q))
            ->setParameter('state_cart', OrderInterface::STATE_CART);

        return $qb;
    }

    public function findRefundedOrdersByRestaurantAndDateRange(LocalBusiness $restaurant, \DateTime $start, \DateTime $end)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->join(PaymentInterface::class, 'p', Join::WITH, 'p.order = o.id')
            ->join(Refund::class,           'r', Join::WITH, 'r.payment = p.id')
            ->andWhere('o.state = :state_fulfilled')
            ->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE')
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')))
            ->setParameter('state_fulfilled', OrderInterface::STATE_FULFILLED)
            ;

        $qb = self::addVendorClause($qb, 'o', $restaurant);

        return $qb->getQuery()->getResult();
    }

    public static function addVendorClause(QueryBuilder $qb, $alias, LocalBusiness $restaurant, $vendorAlias = 'v')
    {
        return $qb
            ->join(OrderVendor::class, $vendorAlias, Join::WITH, sprintf('%s.id = %s.order', $alias, $vendorAlias))
            ->andWhere(sprintf('%s.restaurant = :restaurant', $vendorAlias))
            ->setParameter('restaurant', $restaurant);
    }

    public function fetchNextSeqId(){
        $dbConnection = $this->getEntityManager()->getConnection();
        $nextValQuery = $dbConnection->getDatabasePlatform()->getSequenceNextValSQL('sylius_order_id_seq');
        $id = (int) $dbConnection->executeQuery($nextValQuery)->fetchOne();
        return $id;
    }

    public function findBookmarked(Store $store, UserInterface $user): array
    {
        $qb = $this->deliveryRepository->createQueryBuilder('d')
            ->where('d.store = :store')
            ->join(Order::class, 'o', Join::WITH, 'o = d.order')
            ->join(OrderBookmark::class, 'b', Join::WITH, 'b.order = o')
            ->andWhere('b.owner = :user OR b.role IN (:userRoles)')
            ->orderBy('o.id', 'DESC')
            ->setParameter('store', $store)
            ->setParameter('user', $user)
            ->setParameter('userRoles', $user->getRoles())
        ;

        return $qb->getQuery()->getResult();
    }

    public function findBySubscriptionAndDate(Task\RecurrenceRule $subscription, DateTime $date)
    {
        $qb = $this->createQueryBuilder('o');

        $this->addDateClause($qb, $date);

        return $qb
            ->andWhere('o.subscription = :subscription')
            ->setParameter('subscription', $subscription)
            ->getQuery()
            ->getResult();
    }
}
