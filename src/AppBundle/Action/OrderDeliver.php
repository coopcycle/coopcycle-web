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

        $order->setStatus(Order::STATUS_DELIVERED);

        $this->addEvent($order, Order::STATUS_DELIVERED, $user);

        $this->redis->hdel('orders:delivering', 'order:'.$order->getId());

        $this->redis->publish('couriers:available', $user->getId());

        return $order;
    }
}