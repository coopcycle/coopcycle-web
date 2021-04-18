<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class Deliveries
{
    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function __invoke($data, Request $request)
    {
        $restaurant = $data;

        $qb = $this->objectManager->getRepository(Delivery::class)->createQueryBuilder('d');

        $date = new \DateTime($request->get('date'));

        $qb->join(Order::class, 'o', Expr\Join::WITH, 'o.id = d.order');

        $qb->andWhere('o.state != :state_cart');
        $qb->setParameter('state_cart', Order::STATE_CART);

        $qb->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE');
        $qb->setParameter('range', sprintf('[%s, %s]', $date->format('Y-m-d 00:00:00'), $date->format('Y-m-d 23:59:59')));

        $qb = OrderRepository::addVendorClause($qb, 'o', $data);

        return $qb->getQuery()->getResult();
    }
}
