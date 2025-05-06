<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class TasksProvider implements ProviderInterface
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly iterable $collectionExtensions,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        $resourceClass = $operation->getClass();

        $qb = $this->entityManager->getRepository(Task::class)->createQueryBuilder('o'); // alias 'o' is used by paginator

        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection(
                $qb,
                $queryNameGenerator,
                $resourceClass,
                $operation,
                $context
            );

            if (
                $extension instanceof QueryResultCollectionExtensionInterface
                &&
                $extension->supportsResult($resourceClass, $operation, $context) // @phpstan-ignore arguments.count
            ) {
                return $this->postProcessResult(
                    $extension->getResult($qb, $resourceClass, $operation, $context) // @phpstan-ignore arguments.count
                );
            }
        }

        return $this->postProcessResult($qb->getQuery()->getResult());
    }

    private function postProcessResult(iterable $data): iterable
    {
        $tasks = iterator_to_array($data);

        // for a pickup in a delivery, the serialized weight is the sum of the dropoff weight and the packages are the "sum" of the dropoffs packages
        $sql = <<<SQL
            select
                t_outer.id,
                case
                    WHEN t_outer.delivery_id is not null and t_outer.type = 'PICKUP' THEN
                        (select json_agg(json_build_object(
                            'id', packages_rows.id, 'task_package_id', packages_rows.task_package_id, 'name', packages_rows.name, 'type', packages_rows.name, 'quantity', packages_rows.quantity, 'volume_per_package', packages_rows.volume_units, 'short_code', packages_rows.short_code))
                            FROM
                                (select p.id AS id, MAX(tp.id) AS task_package_id, p.name AS name, p.average_volume_units AS volume_units, p.short_code as short_code, sum(tp.quantity) AS quantity
                                    from task t inner join task_package tp on tp.task_id = t.id
                                    inner join package p on tp.package_id = p.id
                                    where t.delivery_id = t_outer.delivery_id
                                    group by p.id, p.name, p.average_volume_units
                                ) packages_rows)
                    WHEN t_outer.type = 'DROPOFF' THEN
                        (select json_agg(json_build_object(
                            'id', packages_rows.id, 'task_package_id', packages_rows.task_package_id, 'name', packages_rows.name, 'type', packages_rows.name, 'quantity', packages_rows.quantity, 'volume_per_package', packages_rows.volume_units, 'short_code', packages_rows.short_code))
                            FROM
                                (select p.id AS id, MAX(tp.id) AS task_package_id, p.name AS name, p.average_volume_units AS volume_units, p.short_code as short_code, sum(tp.quantity) AS quantity
                                    from task t inner join task_package tp on tp.task_id = t.id
                                    inner join package p on tp.package_id = p.id
                                    where t.id = t_outer.id
                                    group by p.id, p.name, p.average_volume_units
                                ) packages_rows)
                    ELSE
                        NULL
                    END
                    as packages,
                case
                    WHEN t_outer.delivery_id is not null and t_outer.type = 'PICKUP' THEN
                        (select sum(weight) from task t where (t.delivery_id = t_outer.delivery_id))
                    WHEN t_outer.type = 'DROPOFF' THEN
                        t_outer.weight
                    ELSE
                        NULL
                    END
                    as weight
            from task t_outer
            where t_outer.id IN (:taskIds);
        SQL;

        $params = ['taskIds' => array_map(function ($task) {
            return $task->getId();
        }, $tasks)];
        $query = $this->entityManager->getConnection()->executeQuery(
            $sql,
            $params,
            ['taskIds' => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
        $res = $query->fetchAllAssociativeIndexed();

        foreach ($tasks as $task) {
            $input = $res[$task->getId()];
            $task->setPrefetchedPackagesAndWeight([
                    'packages' => json_decode($input['packages'] ?? '[]', true),
                    'weight' => $input['weight']]
            );
        }

        // Optimization: to avoid extra queries preload one-to-many relations that will be used later
        $this->preloadEntities($tasks);

        return $data;
    }

    private function preloadEntities(array $tasks): void
    {
        $preloader = new EntityPreloader($this->entityManager);

        $preloader->preload($tasks, 'incidents');

        $delivery = $preloader->preload($tasks, 'delivery');

        $order = $preloader->preload($delivery, 'order');
        $orderItems = $preloader->preload($order, 'items');
        $preloader->preload($orderItems, 'variant');
    }
}
