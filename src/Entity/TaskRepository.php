<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskList;
use DateTime;
use AppBundle\Utils\Barcode\Barcode;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;

class TaskRepository extends EntityRepository
{
    public function findUnassignedByDate(\DateTime $date)
    {
        $start = clone $date;
        $end = clone $date;

        return self::addRangeClause($this->createQueryBuilder('t'), $start, $end)
            ->andWhere(sprintf('t.%s IS NULL', 'assignedTo'))
            ->getQuery()
            ->getResult();
    }

    public function findByDate(\DateTime $date)
    {
        $start = clone $date;
        $end = clone $date;

        return $this->findByDateRange($start, $end);
    }

    public function findByDateRange(\DateTime $start, \DateTime $end)
    {
        return self::addRangeClause($this->createQueryBuilder('t'), $start, $end)
            ->getQuery()
            ->getResult();
    }

    public function findByDateRangeOrderByPosition(\DateTime $start, \DateTime $end)
    {
        $qb = self::addRangeClause($this->createQueryBuilder('t'), $start, $end)
            ->leftJoin(TaskCollectionItem::class, 'i', Expr\Join::WITH, 'i.task = t.id')
            ->leftJoin(TaskList::class, 'tl', Expr\Join::WITH, 'i.parent = tl.id')
            ->leftJoin(User::class, 'u', Expr\Join::WITH, 'u.id = t.assignedTo')
            ->addOrderBy('u.username', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ;

        return $qb->getQuery()->getResult();
    }

    public static function addRangeClause(QueryBuilder $qb, \DateTime $start, \DateTime $end): QueryBuilder
    {
        // @see https://github.com/martin-georgiev/postgresql-for-doctrine
        // @see https://www.postgresql.org/docs/9.4/rangetypes.html
        // @see https://www.postgresql.org/docs/9.4/functions-range.html

        return $qb->andWhere('OVERLAPS(TSRANGE(t.doneAfter, t.doneBefore), CAST(:range AS tsrange)) = TRUE')
            ->setParameter('range', sprintf('[%s, %s]', $start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')));
    }

    public function findBySubscriptionAndDate(Task\RecurrenceRule $subscription, DateTime $date)
    {
        $start = clone $date;
        $end = clone $date;

        return self::addRangeClause($this->createQueryBuilder('t'), $start, $end)
            ->andWhere('t.recurrenceRule = :subscription')
            ->setParameter('subscription', $subscription)
            ->getQuery()
            ->getResult();
    }

    public function findByBarcode(string $barcode): ?Task
    {
        $barcode = BarcodeUtils::parse($barcode);

        if ($barcode->isInternal() && $barcode->getEntityType() === Barcode::TYPE_TASK) {
            return $this->find($barcode->getEntityId());
        }

        /* TODO: I'm using raw sql here, but doctrine doesn't support json fields and i don't
                 want to install doctrine json functions just for this
            @see https://github.com/ScientaNL/DoctrineJsonFunctions
        */
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT id FROM task WHERE metadata->>'barcode' = :barcode";
        $stmt = $conn->prepare($sql);
        $id = $stmt->executeQuery(['barcode' => $barcode->getRawBarcode()])->fetchOne();
        if ($id) {
            return $this->find($id);
        }
        return null;
    }
}
