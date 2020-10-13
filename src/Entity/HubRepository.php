<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

class HubRepository extends EntityRepository
{
    public function findOneByRestaurant(LocalBusiness $restaurant): ?Hub
    {
        $qb = $this->createQueryBuilder('h')
            ->innerJoin('h.restaurants', 'r')
            ->where('r.id = :restaurant')
            ->setParameter('restaurant', $restaurant)
            ->setMaxResults(1)
        ;

        return $qb->getQuery()->getOneOrNullResult();
    }
}
