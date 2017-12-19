<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;

class OrderRepository extends EntityRepository
{
    public function getWaitingOrdersForRestaurant(Restaurant $restaurant, \DateTime $date = null)
    {
        if (null === $date) {
            $date = new \DateTime('now');
        }

        $qb = $this->createQueryBuilder('o');
        $qb
            ->join(Delivery::class, 'd', Expr\Join::WITH, 'd.order = o.id')
            ->andWhere('o.restaurant = :restaurant')
            ->andWhere($qb->expr()->notIn('d.status', [
                Delivery::STATUS_PICKED,
                Delivery::STATUS_DELIVERED,
            ]))
            ->andWhere('DATE(d.date) >= :date')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('date', $date)
            ->orderBy('d.date', 'ASC')
            ;

        return $qb->getQuery()->getResult();
    }

    public function getHistoryOrdersForRestaurant(Restaurant $restaurant)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->andWhere('o.restaurant = :restaurant')
            ->andWhere($qb->expr()->notIn('o.status', [
                Order::STATUS_WAITING,
                Order::STATUS_ACCEPTED
            ]))
            ->setParameter('restaurant', $restaurant)
            ->orderBy('o.updatedAt', 'DESC')
            ;

        return $qb->getQuery()->getResult();
    }

    public function countByStatus($status)
    {
        $statusList = !is_array($status) ? [$status] : $status;

        $qb = $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->where('o.status IN (:statusList)')
            ->setParameter('statusList', $statusList)
            ;

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findByStatus($status, array $orderBy = null, $limit = null, $offset = null)
    {
        $statusList = !is_array($status) ? [$status] : $status;

        $qb = $this->createQueryBuilder('o')
            ->where('o.status IN (:statusList)')
            ->setParameter('statusList', $statusList)
            ;

        if (null !== $orderBy) {
            foreach ($orderBy as $sort => $order) {
                $qb->orderBy('o.'.$sort, $order);
            }
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        if (null !== $offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
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
