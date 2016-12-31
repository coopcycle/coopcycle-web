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
        $user = $this->getUser();

        // Only couriers can accept orders
        if (!$user->hasRole('ROLE_COURIER')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot accept order', $user->getId()));
        }

        $order = $data;

        // Make sure the courier picking order is authorized
        if ($order->getCourier() !== $this->getUser()) {
            throw new AccessDeniedException();
        }

        $order->setStatus(Order::STATUS_PICKED);

        return $order;
    }
}