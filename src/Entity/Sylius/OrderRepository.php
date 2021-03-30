<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Refund;
use AppBundle\Entity\Sylius\OrderView;
use AppBundle\Entity\Task;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class OrderRepository extends BaseOrderRepository
{
    public function findCartsByRestaurant(LocalBusiness $restaurant)
    {
        return $this->createQueryBuilder('o')
            ->join(Vendor::class, 'v', Join::WITH, 'o.vendor = v.id')
            ->andWhere('o.state = :state')
            ->andWhere('v.restaurant = :restaurant')
            ->setParameter('state', OrderInterface::STATE_CART)
            ->setParameter('restaurant', $restaurant)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOrdersByDate(\DateTime $date)
    {
        $start = clone $date;
        $end   = clone $date;

        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);

        return $this->findOrdersByDateRange($start, $end);
    }

    public function findOrdersByRestaurantAndDateRange(LocalBusiness $restaurant, \DateTime $start, \DateTime $end, bool $includeHubOrders = false)
    {
        $qb = $this
            ->createQueryBuilder('o')
            ->join(Vendor::class, 'v', Join::WITH, 'o.vendor = v.id')
            ->andWhere('v.restaurant = :restaurant')
            ->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE')
            ->andWhere('o.state != :state')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')))
            ->setParameter('state', OrderInterface::STATE_CART)
            ;

        $orders = $qb->getQuery()->getResult();

        if (!$includeHubOrders) {

            return $orders;
        }

        //
        // Add hub orders
        //

        $orderIds = array_map(fn(OrderInterface $r) => $r->getId(), $orders);

        $qb = $this->getEntityManager()->getRepository(OrderView::class)
            ->createQueryBuilder('ov');

        $qb = self::addShippingTimeRangeClause($qb, 'ov', $start, $end);
        $qb->select('ov.id');
        $qb->andWhere('ov.restaurant = :restaurant');
        if (count($orderIds) > 0) {
            $qb->andWhere($qb->expr()->notIn('ov.id', $orderIds));
        }
        $qb->setParameter('restaurant', $restaurant);

        $hubOrderIds = array_map(fn(array $o) => $o['id'], $qb->getQuery()->getArrayResult());

        if (count($hubOrderIds) > 0) {
            $qb = $this
                ->createQueryBuilder('o')
                ->andWhere($qb->expr()->in('o.id', $hubOrderIds));

            $hubOrders = $qb->getQuery()->getResult();

            $orders = array_merge($orders, $hubOrders);
        }

        return $orders;
    }

    public function findOrdersByDateRange(\DateTime $start, \DateTime $end)
    {
        $qb = $this->createQueryBuilder('o');

        $this->addDateRangeClause($qb, $start, $end);

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

    public function search($q)
    {
        $qb = $this->createQueryBuilder('o');

        $qb
            ->join(Customer::class, 'c', Join::WITH, 'o.customer = c.id')
            // ->andWhere('o.state != :state_cart')
            ->add('where', $qb->expr()->orX(
                $qb->expr()->gt('SIMILARITY(o.number, :q)', 0),
                $qb->expr()->gt('SIMILARITY(c.email, :q)', 0)
            ))
            ->add('where', $qb->expr()->neq('o.state', ':state_cart'))
            ->addOrderBy('SIMILARITY(o.number, :q)', 'DESC')
            ->addOrderBy('SIMILARITY(c.email, :q)', 'DESC')
            ->addOrderBy('o.createdAt', 'DESC')
            ->setParameter('q', strtolower($q))
            ->setParameter('state_cart', OrderInterface::STATE_CART);

        $qb->setMaxResults(10);

        return $qb->getQuery()->getResult();
    }

    public function findRefundedOrdersByRestaurantAndDateRange(LocalBusiness $restaurant, \DateTime $start, \DateTime $end)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->join(Vendor::class,           'v', Join::WITH, 'o.vendor = v.id')
            ->join(PaymentInterface::class, 'p', Join::WITH, 'p.order = o.id')
            ->join(Refund::class,           'r', Join::WITH, 'r.payment = p.id')
            ->andWhere('v.restaurant = :restaurant')
            ->andWhere('o.state = :state_fulfilled')
            ->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')))
            ->setParameter('state_fulfilled', OrderInterface::STATE_FULFILLED)
            ;

        return $qb->getQuery()->getResult();
    }
}
