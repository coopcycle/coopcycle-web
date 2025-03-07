<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollectionItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;

class TourRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    )
    {
        parent::__construct($registry, Tour::class);
    }

    public function findOneByTask(Task $task): ?Tour
    {
        return $this->createQueryBuilder('to')
            ->join(TaskCollectionItem::class, 'tci', Expr\Join::WITH, 'tci.parent = to.id')
            ->where('tci.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByNameAndDate(string $name, \DateTime $date): ?Tour
    {
        return $this->createQueryBuilder('to')
            ->where('to.name = :name')
            ->andWhere('to.date = :date')
            ->setParameter('name', $name)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
