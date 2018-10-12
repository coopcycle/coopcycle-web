<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderRepository as BaseOrderRepository;

class OrderRepository extends BaseOrderRepository
{
    public function findByShippedAt(\DateTime $date)
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->where('DATE(o.shippedAt) = :date')
            ->setParameter('date', $date->format('Y-m-d'));

        return $qb->getQuery()->getResult();
    }
}
