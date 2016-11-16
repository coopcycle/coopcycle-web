<?php

namespace AppBundle\Action;

use AppBundle\Entity\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OrderPick
{
    use OrderActionTrait;

    /**
     * @Route(
     *     name="order_pick",
     *     path="/orders/{id}/pick",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="pick"}
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