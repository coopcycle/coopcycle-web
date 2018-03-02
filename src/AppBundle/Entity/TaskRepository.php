<?php

namespace AppBundle\Entity;

use FOS\UserBundle\Model\UserInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

class TaskRepository extends EntityRepository
{
    public function findByDate(\DateTime $date)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('DATE(t.doneBefore) = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function findUnassigned(\DateTime $date)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.assignedTo IS NULL')
            ->andWhere('DATE(t.doneBefore) = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function findAssigned(\DateTime $date)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.assignedTo IS NOT NULL')
            ->andWhere('DATE(t.doneBefore) = :date')
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
