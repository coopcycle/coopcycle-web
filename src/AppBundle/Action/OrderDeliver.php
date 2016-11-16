<?php

namespace AppBundle\Action;

use AppBundle\Entity\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OrderDeliver
{
    use OrderActionTrait;

    /**
     * @Route(
     *     name="order_deliver",
     *     path="/orders/{id}/deliver",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="deliver"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        if ($data->getCourier() !== $this->getUser()) {
            throw new AccessDeniedException();
        }

        return $data;
    }
}