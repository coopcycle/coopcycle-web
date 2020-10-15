<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Task;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class OrderRepository extends BaseOrderRepository
{
    public function findCartsByRestaurant(LocalBusiness $restaurant)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.state = :state')
            ->andWhere('o.restaurant = :restaurant')
            ->setParameter('state', OrderInterface::STATE_CART)
            ->setParameter('restaurant', $restaurant)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByDate(\DateTime $date)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.restaurant IS NOT NULL')
            ->andWhere('o.state != :state')
            ->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE')
            ->setParameter('state', OrderInterface::STATE_CART)
            ->setParameter('range', sprintf('[%s, %s]', $date->format('Y-m-d 00:00:00'), $date->format('Y-m-d 23:59:59')))
            ;

        return $qb->getQuery()->getResult();
    }

    public function findOrdersByRestaurantAndDateRange(LocalBusiness $restaurant, \DateTime $start, \DateTime $end)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.restaurant = :restaurant')
            ->andWhere('o.state != :state_cart')
            ->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('state_cart', OrderInterface::STATE_CART)
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')))
            ->addOrderBy('o.shippingTimeRange', 'DESC')
            ;

        return $qb->getQuery()->getResult();
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

    public function findFulfilledOrdersByDateRange(\DateTime $start, \DateTime $end)
    {
        $qb = $this->createQueryBuilder('o');

        $this->addDateRangeClause($qb, $start, $end);

        $qb
            ->andWhere('o.state = :state_fulfilled')
            ->setParameter('state_fulfilled', OrderInterface::STATE_FULFILLED);

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
}
