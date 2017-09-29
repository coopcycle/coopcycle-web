<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;

class OrderRepository extends EntityRepository
{
    private static function extractTime($interval)
    {
        preg_match('/([0-9]+):([0-9]+):[0-9]+\.?([0-9]*)/', $interval, $matches);

        $hours = $matches[1];
        $minutes = $matches[2];

        return [$hours, $minutes];
    }

    public function getWaitingOrders()
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->join(Delivery::class, 'd', Expr\Join::WITH, 'd.order = o.id')
            ->add('where', $qb->expr()->in('o.status', [
                Order::STATUS_WAITING,
                Order::STATUS_ACCEPTED
            ]))
            ->orderBy('d.date', 'ASC')
            ;

        return $qb->getQuery()->getResult();
    }

    public function getDeliveryTimes(ApiUser $courier)
    {
        $qb = $this->getEntityManager()
            ->getRepository(OrderEvent::class)
            ->createQueryBuilder('accptd')
            ->select('o.id')
            ->addSelect('(pckd.createdAt - accptd.createdAt) AS pickup_interval')
            ->addSelect('(dlvrd.createdAt - accptd.createdAt) AS delivery_interval')
            ->innerJoin(OrderEvent::class, 'pckd', Expr\Join::WITH, 'accptd.order = pckd.order')
            ->innerJoin(OrderEvent::class, 'dlvrd', Expr\Join::WITH, 'accptd.order = dlvrd.order')
            ->join(Order::class, 'o', Expr\Join::WITH, 'accptd.order = o.id')
            ->andWhere('o.courier = :courier')
            ->andWhere('o.status = :delivered')
            ->andWhere('accptd.eventName = :accepted')
            ->andWhere('pckd.eventName = :picked')
            ->andWhere('dlvrd.eventName = :delivered')
            ->setParameter('courier', $courier)
            ->setParameter('accepted', Order::STATUS_ACCEPTED)
            ->setParameter('picked', Order::STATUS_PICKED)
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ->orderBy('o.createdAt', 'DESC')
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

    public function getAverageDeliveryTime(ApiUser $courier)
    {
        // SELECT AVG(o0_.created_at - o1_.created_at) AS sclr_0
        // FROM order_event o1_
        // INNER JOIN order_event o0_ ON (o1_.order_id = o0_.order_id)
        // WHERE o1_.courier_id = ?
        // AND o1_.event_name = ? AND o0_.event_name = ?

        $qb = $this->getEntityManager()
            ->getRepository(OrderEvent::class)
            ->createQueryBuilder('oe')
            ->select('AVG(oe2.createdAt - oe.createdAt)')
            ->innerJoin(OrderEvent::class, 'oe2', Expr\Join::WITH, 'oe.order = oe2.order')
            ->join(Order::class, 'o', Expr\Join::WITH, 'oe.order = o.id')
            ->where('oe.courier = :courier')
            ->andWhere('o.status = :delivered')
            ->andWhere('oe.eventName = :accepted')
            ->andWhere('oe2.eventName = :delivered')
            ->setParameter('courier', $courier)
            ->setParameter('accepted', Order::STATUS_ACCEPTED)
            ->setParameter('delivered', Order::STATUS_DELIVERED)
            ;

        // 01:13:20.136364
        if ($interval = $qb->getQuery()->getSingleScalarResult()) {
            list($hours, $minutes) = self::extractTime($interval);

            return $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
        }
    }

    public function countByStatus($status)
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ;

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findByRestaurants($restaurants)
    {
        $restaurantIds = array_map(function ($restaurant) {
            return $restaurant->getId();
        }, $restaurants->toArray());

        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.restaurant IN (:restaurantIds)')
            ->setParameter('restaurantIds', $restaurantIds)
            ->orderBy('o.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function countAll()
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
