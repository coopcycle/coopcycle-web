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
            ->where('DATE(o.shippedAt) = :date')
            ->setParameter('date', $date->format('Y-m-d'));

        return $qb->getQuery()->getResult();
    }
}
