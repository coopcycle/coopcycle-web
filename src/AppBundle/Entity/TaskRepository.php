<?php

namespace AppBundle\Entity;

use FOS\UserBundle\Model\UserInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

class TaskRepository extends EntityRepository
{
    public function findByUserAndDate(UserInterface $user, \DateTime $date)
    {
        return $this->createQueryBuilder('t')
            ->join(TaskAssignment::class, 'ta', Expr\Join::WITH, 't.id = ta.task')
            ->andWhere('DATE(t.doneAfter) = :date')
            ->andWhere('ta.courier = :courier')
            ->orderBy('ta.position', 'ASC')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('courier', $user)
            ->getQuery()
            ->getResult();
    }

    public function findAssigned(\DateTime $date)
    {
        return $this->createQueryBuilder('t')
            ->join(TaskAssignment::class, 'ta', Expr\Join::WITH, 't.id = ta.task')
            ->andWhere('DATE(t.doneAfter) = :date')
            ->orderBy('ta.position', 'ASC')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function findLinked(Task $task)
    {
        $linked = [];

        if ($task->hasPrevious()) {
            // TODO Recursion
            $linked[] = $task->getPrevious();
        } else {
            $linked = $this->createQueryBuilder('t')
                ->andWhere('t.previous = :task')
                ->setParameter('task', $task)
                ->getQuery()
                ->getResult();
        }

        return $linked;
    }
}
