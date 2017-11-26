<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

class RestaurantRepository extends EntityRepository
{
    private function createNearbyQueryBuilder($latitude, $longitude, $distance = 3000)
    {
        $qb = $this->createQueryBuilder('r');

        self::addNearbyQueryClause($qb, $latitude, $longitude, $distance);

        return $qb;
    }

    public static function addNearbyQueryClause(QueryBuilder $qb, $latitude, $longitude, $distance = 3000)
    {
        $qb->innerJoin($qb->getRootAlias() . '.address', 'a', Expr\Join::WITH);

        $geomFromText = new Expr\Func('ST_GeomFromText', array(
            $qb->expr()->literal("POINT({$latitude} {$longitude})"),
            '4326'
        ));

        $dist = new Expr\Func('ST_Distance', array(
            $geomFromText,
            'a.geo'
        ));

        // Add calculated distance field
        $qb->addSelect($dist . ' AS HIDDEN distance');

        $within = new Expr\Func('ST_DWithin', array(
            $geomFromText,
            'a.geo',
            $distance
        ));

        $qb->add('where', $qb->expr()->eq(
            $within,
            $qb->expr()->literal(true)
        ));
    }

    public function countNearby($latitude, $longitude, $distance = 5000, $limit = 10, $offset = 0)
    {
        $qb = $this->createNearbyQueryBuilder($latitude, $longitude, $distance);

        $qb->select($qb->expr()->count('r'));

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findNearby($latitude, $longitude, $distance = 5000, $limit = 10, $offset = 0)
    {
        $qb = $this->createNearbyQueryBuilder($latitude, $longitude, $distance);

        $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $qb->orderBy('distance');

        return $qb->getQuery()->getResult();
    }

    public function search($q)
    {
        $qb = $this->createQueryBuilder('r');

        // TODO Use lowercase
        $qb
            ->where('r.name LIKE :q')
            ->setParameter('q', '%' . $q . '%');

        return $qb->getQuery()->getResult();
    }
}
