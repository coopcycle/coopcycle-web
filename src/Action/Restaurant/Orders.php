<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Symfony\Component\HttpFoundation\Request;

class Orders
{
    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function __invoke($data, Request $request)
    {
        $restaurant = $data;

        // We need to change the "_api_resource_class" attributes,
        // so that @context equals "/api/contexts/Order"
        $request->attributes->set('_api_resource_class', Order::class);

        $qb = $this->objectManager->getRepository(Order::class)->createQueryBuilder('o');

        $date = new \DateTime($request->get('date'));

        $qb->andWhere('o.restaurant = :restaurant');
        $qb->setParameter('restaurant', $data);

        $qb->andWhere('OVERLAPS(o.shippingTimeRange, CAST(:range AS tsrange)) = TRUE');
        $qb->setParameter('range', sprintf('[%s, %s]', $date->format('Y-m-d 00:00:00'), $date->format('Y-m-d 23:59:59')));

        return $qb->getQuery()->getResult();
    }
}
