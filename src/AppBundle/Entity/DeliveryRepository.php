<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;

class DeliveryRepository extends EntityRepository
{
    private static function extractTime($interval)
    {
        preg_match('/([0-9]+):([0-9]+):[0-9]+\.?([0-9]*)/', $interval, $matches);

        $hours = $matches[1];
        $minutes = $matches[2];

        return [$hours, $minutes];
    }

    public function getDeliveryTimes(ApiUser $courier)
    {
        $qb = $this->getEntityManager()
            ->getRepository(DeliveryEvent::class)
            ->createQueryBuilder('dispatched')
            ->select('d.id')
            ->addSelect('(picked.createdAt - dispatched.createdAt) AS pickup_interval')
            ->addSelect('(delivered.createdAt - dispatched.createdAt) AS delivery_interval')

            ->innerJoin(DeliveryEvent::class, 'picked', Expr\Join::WITH, 'dispatched.delivery = picked.delivery')
            ->innerJoin(DeliveryEvent::class, 'delivered', Expr\Join::WITH, 'dispatched.delivery = delivered.delivery')
            ->join(Delivery::class, 'd', Expr\Join::WITH, 'dispatched.delivery = d.id')

            ->andWhere('d.courier = :courier')
            ->andWhere('d.status = :delivered')
            ->andWhere('dispatched.eventName = :dispatched')
            ->andWhere('picked.eventName = :picked')
            ->andWhere('delivered.eventName = :delivered')

            ->setParameter('courier', $courier)
            ->setParameter('dispatched', Delivery::STATUS_DISPATCHED)
            ->setParameter('picked', Delivery::STATUS_PICKED)
            ->setParameter('delivered', Delivery::STATUS_DELIVERED)
            ->orderBy('d.id', 'DESC') // TODO use createdAt
            ->setMaxResults(15)
            ;

        $rows = $qb->getQuery()->getResult();

        foreach ($rows as $key => $row) {
            list($hours, $minutes) = self::extractTime($row['pickup_interval']);
            $rows[$key]['pickup_time'] = (int) $hours * 60 + $minutes;
            list($hours, $minutes) = self::extractTime($row['delivery_interval']);
            $rows[$key]['delivery_time'] = (int) $hours * 60 + $minutes;
        }

        return array_reverse($rows);
    }

    /**
     * Returns the average delivery time for $courier.
     * It is calculated from the time when the delivery is dispatched.
     */
    public function getAverageDeliveryTime(ApiUser $courier)
    {
        // SELECT AVG(o0_.created_at - o1_.created_at) AS sclr_0
        // FROM order_event o1_
        // INNER JOIN order_event o0_ ON (o1_.order_id = o0_.order_id)
        // WHERE o1_.courier_id = ?
        // AND o1_.event_name = ? AND o0_.event_name = ?

        $qb = $this->getEntityManager()
            ->getRepository(DeliveryEvent::class)
            ->createQueryBuilder('de')
            ->select('AVG(de2.createdAt - de.createdAt)')
            ->innerJoin(DeliveryEvent::class, 'de2', Expr\Join::WITH, 'de.delivery = de2.delivery')
            ->join(Delivery::class, 'd', Expr\Join::WITH, 'de.delivery = d.id')
            ->where('de.courier = :courier')
            ->andWhere('d.status = :delivered')
            ->andWhere('de.eventName = :dispatched')
            ->andWhere('de2.eventName = :delivered')
            ->setParameter('courier', $courier)
            ->setParameter('dispatched', Delivery::STATUS_DISPATCHED)
            ->setParameter('delivered', Delivery::STATUS_DELIVERED)
            ;

        // 01:13:20.136364
        if ($interval = $qb->getQuery()->getSingleScalarResult()) {
            list($hours, $minutes) = self::extractTime($interval);

            return $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
        }

        return '0min';
    }
}
