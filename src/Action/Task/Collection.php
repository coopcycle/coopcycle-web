<?php

namespace AppBundle\Action\Task;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Paginator;
use Doctrine\ORM\EntityManagerInterface;

class Collection extends Base
{
    public function __invoke(Paginator|array $data, EntityManagerInterface $entityManager)
    {
        // for a pickup in a delivery, the serialized weight is the sum of the dropoff weight and the packages are the "sum" of the dropoffs packages
        $sql = "
            select
                t_outer.id,
                case
                    WHEN t_outer.delivery_id is not null and t_outer.type = 'PICKUP' THEN
                        (select json_agg(json_build_object(
                            'name', packages_rows.name, 'type', packages_rows.name, 'quantity', packages_rows.quantity, 'volume_per_package', packages_rows.volume_units, 'short_code', packages_rows.short_code))
                            FROM
                                (select p.name AS name, p.average_volume_units AS volume_units, p.short_code as short_code, sum(tp.quantity) AS quantity
                                    from task t inner join task_package tp on tp.task_id = t.id
                                    inner join package p on tp.package_id = p.id
                                    where t.delivery_id = t_outer.delivery_id
                                    group by p.id, p.name, p.average_volume_units
                                ) packages_rows)
                    WHEN t_outer.type = 'DROPOFF' THEN
                        (select json_agg(json_build_object(
                            'name', packages_rows.name, 'type', packages_rows.name, 'quantity', packages_rows.quantity, 'volume_per_package', packages_rows.volume_units, 'short_code', packages_rows.short_code))
                            FROM
                                (select p.name AS name, p.average_volume_units AS volume_units, p.short_code as short_code, sum(tp.quantity) AS quantity
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
        ";
        
        $params = ['taskIds' => array_map(function ($task) { return $task->getId(); }, iterator_to_array($data))];
        $query = $entityManager->getConnection()->executeQuery(
            $sql,
            $params,
            ['taskIds' =>  \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
        $res = $query->fetchAllAssociativeIndexed();

        foreach($data as $task) {
            $input = $res[$task->getId()];
            $task->setPrefetchedPackagesAndWeight([
                'packages' => json_decode($input['packages']),
                'weight' => $input['weight']]
            );
        }

        return $data;
    }
}
