<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Vendor;
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
        $date = new \DateTime($request->get('date'));

        $start = clone $date;
        $end = clone $date;

        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);

        return $this->objectManager->getRepository(Order::class)
            ->findOrdersByRestaurantAndDateRange($data, $start, $end,
                // We do *NOT* include the hub orders
                $includeHubOrders = false);
    }
}
