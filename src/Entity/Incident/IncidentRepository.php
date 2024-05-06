<?php

namespace AppBundle\Entity\Incident;

use AppBundle\Entity\Sylius\OrderVendor;
use Doctrine\ORM\EntityRepository;
/**
 * @extends EntityRepository<Incident>
 */
class IncidentRepository extends EntityRepository {


    //TODO: Move this function in a helper class
    private function transformArray(array $inputArray): array {
        $outputArray = [];

        foreach ($inputArray as $key => $value) {
            $keys = explode("_", $key);
            /** @var array $tempArray */
            $tempArray = &$outputArray;

            foreach ($keys as $nestedKey) {
                if (!isset($tempArray[$nestedKey])) {
                    $tempArray[$nestedKey] = [];
                }
                $tempArray = &$tempArray[$nestedKey];
            }
            $tempArray = is_array($value) ? $this->transformArray($value) : $value;
        }

        return $outputArray;
    }

    public function getAllIncidents(): array {

        $q = $this->createQueryBuilder('i')
            ->select(
                // Incident
                'i.id', 'i.title', 'i.description',
                'i.createdAt', 'i.status', 'i.priority',
                'au.id as author_id',
                'au.username as author_username',
                't.id as task_id',
                't.status as task_status',
                't.type as task_type',
                'd.id as delivery_id',
                // Foodtech
                'o.id as order_id',
                'r.id as order_restaurant_id',
                'r.name as order_restaurant_name',
                'c.id as order_customer_id',
                'c.username as order_customer_username',
                // Last-mile
                's.id as store_id',
                's.name as store_name'
                //TODO: Add transporter name in select.
            )
            ->orderBy('i.createdAt', 'DESC')
            ->leftJoin('i.task', 't')
            ->leftJoin('t.delivery', 'd')
            ->leftJoin('d.order', 'o')
            ->leftJoin('d.store', 's')
            ->leftJoin('i.createdBy', 'au')
            ->leftJoin(OrderVendor::class, 'v', 'WITH', 'v.order = o.id')
            ->leftJoin('v.restaurant', 'r')
            ->leftJoin('o.customer', 'cu')
            ->leftJoin('cu.user', 'c')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery();

            return $this->transformArray($q->getResult());
    }

}
