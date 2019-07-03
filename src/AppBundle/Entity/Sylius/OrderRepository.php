<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class OrderRepository extends BaseOrderRepository
{
    public function findCartsByRestaurant(Restaurant $restaurant)
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

    public function findByShippedAt(\DateTime $date)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.restaurant IS NOT NULL')
            ->andWhere('o.state != :state')
            ->andWhere('DATE(o.shippedAt) = :date')
            ->setParameter('state', OrderInterface::STATE_CART)
            ->setParameter('date', $date->format('Y-m-d'));

        return $qb->getQuery()->getResult();
    }

    public function findOrdersByRestaurantAndDateRange(Restaurant $restaurant, \DateTime $start, \DateTime $end)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.restaurant = :restaurant')
            ->andWhere('o.state != :state_cart')
            ->andWhere('DATE(o.shippedAt) >= :start')
            ->andWhere('DATE(o.shippedAt) <= :end')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('state_cart', OrderInterface::STATE_CART)
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'));

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

    public function findByUser($user)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.state != :state_cart')
            ->andWhere('o.customer = :customer')
            ->setParameter('state_cart', OrderInterface::STATE_CART)
            ->setParameter('customer', $user)
            ->addOrderBy('o.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function addDateRangeClause(QueryBuilder $qb, \DateTime $start, \DateTime $end)
    {
        $qb
            ->andWhere('DATE(o.shippedAt) >= :start')
            ->andWhere('DATE(o.shippedAt) <= :end')
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'));

        return $this;
    }

    public function addDateClause(QueryBuilder $qb, \DateTime $date)
    {
        $qb
            ->andWhere('DATE(o.shippedAt) = :date')
            ->setParameter('date', $date->format('Y-m-d'));

        return $this;
    }

    public function countByCustomerAndCoupon(UserInterface $customer, PromotionCouponInterface $coupon): int
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
}
