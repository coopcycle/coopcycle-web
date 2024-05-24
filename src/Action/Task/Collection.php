<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Symfony\Component\HttpFoundation\Request;

class Collection extends Base
{
    public function __invoke($data, EntityManagerInterface $entityManager)
    {

        $sql = "
            select
                t_outer.id,
                case
                    WHEN t_outer.delivery_id is not null and t_outer.type = 'PICKUP' THEN
                        (select json_agg(json_build_object(
                            'name', packages_rows.name, 'type', packages_rows.name, 'quantity', packages_rows.quantity))
                            FROM
                                (select p.name AS name, sum(tp.quantity) AS quantity
                                    from task t inner join task_package tp on tp.task_id = t.id
                                    inner join package p on tp.package_id = p.id
                                    where t.delivery_id = t_outer.delivery_id
                                    group by p.id
                                ) packages_rows)
                    WHEN t_outer.type = 'DROPOFF' THEN
                        (select json_agg(json_build_object(
                            'name', packages_rows.name, 'type', packages_rows.name, 'quantity', packages_rows.quantity))
                            FROM
                                (select p.name AS name, sum(tp.quantity) AS quantity
                                    from task t inner join task_package tp on tp.task_id = t.id
                                    inner join package p on tp.package_id = p.id
                                    where t.id = t_outer.id
                                    group by p.id
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
            where t_outer.id = 385;
        ";



        $stmt = $entityManager->getConnection()->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetchAll();

        foreach($data as $task) {
            $input = array_filter(
                $res,
                function ($item) use ($task) {
                    return $item['id'] === 385;
                }
            )[0];
            $task->setPrefetchedPackagesAndWeight([
                'packages' => json_decode($input['packages']),
                'weight' => $input['weight']]
            );
        }

        return $data;
    }
}
