<?php

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Sylius\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

class RecurrenceRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurrenceRule::class);
    }

    public function findAllTaskBasedRule(): array
    {
        $qb = $this->createQueryBuilder('rr')
            ->leftJoin(Order::class, 'o', Join::WITH, 'o.subscription = rr')
            ->where('o.id IS NULL');

        $query = $qb->getQuery();

        return $query->execute();
    }

    public function findAllSubscriptions(): array
    {
        // At the moment, subscription always has at least one order
        $qb = $this->createQueryBuilder('rr')
            ->leftJoin(Order::class, 'o', Join::WITH, 'o.subscription = rr')
            ->where('o.id IS NOT NULL');

        $query = $qb->getQuery();

        return $query->execute();
    }
}
