<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;

class UserRepository extends EntityRepository
{
    public function search($q)
    {
        $qb = $this->createQueryBuilder('u');

        $qb
            ->join(Customer::class, 'c', Expr\Join::WITH, 'u.customer = c.id')
            ->add('where', $qb->expr()->orX(
                $qb->expr()->gt('SIMILARITY(u.username, :q)', 0),
                $qb->expr()->gt('SIMILARITY(u.email, :q)', 0),
                $qb->expr()->gt('SIMILARITY(c.firstName, :q)', 0),
                $qb->expr()->gt('SIMILARITY(c.lastName, :q)', 0)
            ))
            ->addOrderBy('SIMILARITY(u.username, :q)', 'DESC')
            ->addOrderBy('SIMILARITY(u.email, :q)', 'DESC')
            ->addOrderBy('SIMILARITY(c.firstName, :q)', 'DESC')
            ->addOrderBy('SIMILARITY(c.lastName, :q)', 'DESC')
            ->setParameter('q', strtolower($q));

        $qb->setMaxResults(10);

        return $qb->getQuery()->getResult();
    }
}
