<?php

namespace AppBundle\Action;

use AppBundle\Entity\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;

class MyOrders
{
    use OrderActionTrait;

    /**
     * @Route(
     *     name="my_orders",
     *     path="/me/orders",
     *     defaults={
     *         "_api_resource_class"=Order::class,
     *         "_api_collection_operation_name"="my_orders"
     *     }
     * )
     * @Method("GET")
     */
    public function __invoke($data)
    {
        return $this->doctrine
            ->getRepository('AppBundle:Order')
            ->findBy(['customer' => $this->getUser()], ['createdAt' => 'DESC'], 30);
    }
}