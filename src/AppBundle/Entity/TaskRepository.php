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
            ->andWhere('DATE(t.doneBefore) = :date')
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
            ->andWhere('DATE(t.doneBefore) = :date')
            ->orderBy('ta.position', 'ASC')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }
}
