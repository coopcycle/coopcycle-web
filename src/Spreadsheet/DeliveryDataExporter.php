<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskRepository;
use Doctrine\ORM\Query\Expr;

final class DeliveryDataExporter extends AbstractDataExporter
{
    protected function getData(\DateTime $start, \DateTime $end): array
    {
        $qb = $this->entityManager->getRepository(Delivery::class)
            ->createQueryBuilder('d');

        $qb
            ->join(TaskCollectionItem::class, 'i', Expr\Join::WITH, 'i.parent = d.id')
            ->join(Task::class, 't', Expr\Join::WITH, 'i.task = t.id')
            ;

        $qb = TaskRepository::addRangeClause($qb, $start, $end);

        return $qb->getQuery()->getResult();
    }
}
