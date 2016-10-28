<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

class OrderRepository extends EntityRepository
{
    public function findByRestaurants($restaurants)
    {
        $restaurantIds = array_map(function($restaurant) {
            return $restaurant->getId();
        }, $restaurants->toArray());

        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.restaurant IN (:restaurantIds)')
            ->setParameter('restaurantIds', $restaurantIds)
            ->orderBy('o.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }
}