<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;

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
            ->andWhere('o.state != :state')
            ->andWhere('DATE(o.shippedAt) = :date')
            ->setParameter('state', OrderInterface::STATE_CART)
            ->setParameter('date', $date->format('Y-m-d'));

        return $qb->getQuery()->getResult();
    }

    public function findOrdersByDateRange(\DateTime $start, \DateTime $end)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.state != :state_cart')
            ->andWhere('DATE(o.shippedAt) >= :start')
            ->andWhere('DATE(o.shippedAt) <= :end')
            ->setParameter('state_cart', OrderInterface::STATE_CART)
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'));

        return $qb->getQuery()->getResult();
    }
}
