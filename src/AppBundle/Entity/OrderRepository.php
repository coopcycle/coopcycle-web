<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;

class OrderRepository extends EntityRepository
{
    public function getWaitingOrdersForRestaurant(Restaurant $restaurant, \DateTime $date = null)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->join(Delivery::class, 'd', Expr\Join::WITH, 'd.order = o.id')
            ->andWhere('o.restaurant = :restaurant')
            ->andWhere($qb->expr()->in('o.status', [
                Order::STATUS_WAITING,
                Order::STATUS_ACCEPTED
            ]))
            ->setParameter('restaurant', $restaurant)
            ->orderBy('d.date', 'ASC')
            ;

        if ($date) {
            $qb
                ->andWhere('DATE(d.date) = :date')
                ->setParameter('date', $date->format('Y-m-d'));
        }

        return $qb->getQuery()->getResult();
    }

    public function getHistoryOrdersForRestaurant(Restaurant $restaurant)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.restaurant = :restaurant')
            ->andWhere($qb->expr()->notIn('o.status', [
                Order::STATUS_WAITING,
                Order::STATUS_ACCEPTED
            ]))
            ->setParameter('restaurant', $restaurant)
            ->orderBy('o.updatedAt', 'DESC')
            ;

        return $qb->getQuery()->getResult();
    }

    public function countByStatus($status)
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ;

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findByRestaurants($restaurants)
    {
        $restaurantIds = array_map(function ($restaurant) {
            return $restaurant->getId();
        }, $restaurants->toArray());

        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.restaurant IN (:restaurantIds)')
            ->setParameter('restaurantIds', $restaurantIds)
            ->orderBy('o.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function countAll()
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
