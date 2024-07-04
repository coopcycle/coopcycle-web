<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollectionItem;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;

class TourRepository extends EntityRepository
{
    public function findOneByTask(Task $task)
    {
        return $this->createQueryBuilder('to')
            ->join(TaskCollectionItem::class, 'tci', Expr\Join::WITH, 'tci.parent = to.id')
            ->where('tci.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
