<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;

class ApiUserRepository extends EntityRepository
{
    public function search($q)
    {
        $qb = $this->createQueryBuilder('u');

        $qb
            ->add('where', $qb->expr()->orX(
                $qb->expr()->gt('SIMILARITY(u.username, :q)', 0),
                $qb->expr()->gt('SIMILARITY(u.email, :q)', 0),
                $qb->expr()->gt('SIMILARITY(u.givenName, :q)', 0),
                $qb->expr()->gt('SIMILARITY(u.familyName, :q)', 0)
            ))
            ->addOrderBy('SIMILARITY(u.username, :q)', 'DESC')
            ->addOrderBy('SIMILARITY(u.email, :q)', 'DESC')
            ->addOrderBy('SIMILARITY(u.givenName, :q)', 'DESC')
            ->addOrderBy('SIMILARITY(u.familyName, :q)', 'DESC')
            ->setParameter('q', strtolower($q));

        return $qb->getQuery()->getResult();
    }
}
