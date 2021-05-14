<?php

namespace AppBundle\Api\Filter;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryBuilderHelper;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Join;

class DeliveryOrderFilter extends OrderFilter
{
    protected function filterProperty(string $property, $direction, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        if (Delivery::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $parts = $queryBuilder->getDQLPart('join');

        $itemsAlias = null;
        if (isset($parts[$rootAlias])) {
            foreach ($parts[$rootAlias] as $join) {
                /** @var Join $join */
                if (sprintf('%s.items', $rootAlias) === $join->getJoin()) {
                    $itemsAlias = $join->getAlias();
                    break;
                }
            }
        }

        if (null === $itemsAlias) {
            $itemsAlias = $queryNameGenerator->generateJoinAlias('items');
            $queryBuilder->innerJoin(sprintf('%s.items', $rootAlias), $itemsAlias, Join::WITH);
        }

        $expr = $queryBuilder->expr();

        // WARNING
        // The result set returned by Doctrine is like below
        //
        //  id | task_id | task_type | position
        // ----+---------+-----------+----------
        //  13 |      26 | PICKUP    |        0
        //  13 |      27 | DROPOFF   |        1
        //  15 |      30 | PICKUP    |        0
        //  15 |      31 | DROPOFF   |        1
        //  16 |      32 | PICKUP    |        0
        //  16 |      33 | DROPOFF   |        1
        //  14 |      28 | PICKUP    |        0
        //  14 |      29 | DROPOFF   |        1
        //  12 |      24 | PICKUP    |        0
        //  12 |      25 | DROPOFF   |        1
        //  11 |      22 | PICKUP    |        0
        //  11 |      23 | DROPOFF   |        1
        //
        // This allows Doctrine to hydrate the Delivery at once
        // It seems important to keep the order of rows (pickup, dropoff)
        // As we want to order by dropoff date,
        // we need to have the dropoff date *BOTH* in the pickup & dropoff row
        //
        // This class will add a JOIN clause like below
        //
        // LEFT JOIN task ON (task_collection_item.parent_id = task.delivery_id AND task.type = 'DROPOFF')

        $taskAlias = $queryNameGenerator->generateJoinAlias('task');

        $condition = $expr->andX(
            $expr->eq(sprintf('%s.parent', $itemsAlias), sprintf('%s.delivery', $taskAlias)),
            $expr->eq(sprintf('%s.type', $taskAlias), ':task_type')
        );
        $queryBuilder->leftJoin(Task::class, $taskAlias, Join::WITH, $condition);
        $queryBuilder->setParameter('task_type', 'DROPOFF');

        $queryBuilder->addOrderBy(sprintf('%s.doneBefore', $taskAlias), $direction);

        // TODO Do not rely on task.delivery_id
        // It could be rewritten using a subquery like below, but left join + subquery does not work in Doctrine DQL
        // LEFT JOIN (
        //   SELECT task.* FROM task INNER JOIN task_collection_item task_collection_item.task_id = task.id WHERE task.type = 'DROPOFF'
        // ) AS dropoff_task ON dropoff_task.parent_id = delivery.id
    }
}
