<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

class RestaurantRepository extends EntityRepository
{
    public function findNearby($latitude, $longitude, $distance = 5000)
    {
        $qb = $this->createQueryBuilder('r');

        $geomFromText = new Expr\Func('ST_GeomFromText', array(
            $qb->expr()->literal("POINT({$latitude} {$longitude})"),
            '4326'
        ));

        $within = new Expr\Func('ST_DWithin', array(
            $geomFromText,
            'r.geo',
            $distance
        ));

        $qb->add('where', $qb->expr()->eq(
            $within,
            $qb->expr()->literal(true)
        ));

        return $qb->getQuery()->getResult();
    }

    // public function findByRestaurants($restaurants)
    // {
    //     $qb = $this->createQueryBuilder('r')
    //         ->andWhere('r.workingHours IN (:workingHours)')
    //         ->setParameter('workingHours', $workingHours);
    // }
}