<?php

namespace AppBundle\Entity;

use FOS\UserBundle\Model\UserInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

class TaskRepository extends EntityRepository
{
    private function createDateAwareQueryBuilder(\DateTime $date)
    {
        return $this->createQueryBuilder('t')
            ->andWhere(':date >= DATE(t.doneAfter)')
            ->andWhere(':date <= DATE(t.doneBefore)')
            ->setParameter('date', $date->format('Y-m-d'));
    }

    public function findByDate(\DateTime $date)
    {
        return $this->createDateAwareQueryBuilder($date)
            ->getQuery()
            ->getResult();
    }

    public function findUnassigned(\DateTime $date)
    {
        return $this->createDateAwareQueryBuilder($date)
            ->andWhere('t.assignedTo IS NULL')
            ->getQuery()
            ->getResult();
    }

    public function findTasksByDateRange(\DateTime $start, \DateTime $end)
    {
        // @see https://github.com/martin-georgiev/postgresql-for-doctrine
        // @see https://www.postgresql.org/docs/9.4/rangetypes.html
        // @see https://www.postgresql.org/docs/9.4/functions-range.html

        return $this->createQueryBuilder('t')
            ->andWhere('OVERLAPS(TSRANGE(t.doneAfter, t.doneBefore), CAST(:range AS tsrange)) = TRUE')
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')))
            ->getQuery()
            ->getResult();
    }
}
