<?php

namespace AppBundle\Action;

use AppBundle\Entity\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OrderAccept
{
    use OrderActionTrait;

    /**
     * @Route(
     *     name="order_accept",
     *     path="/orders/{id}/accept",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="accept"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        // TODO Check if order is not accepted yet, etc...

        $user = $this->getUser();

        // Only couriers can accept orders
        if (!$user->hasRole('ROLE_COURIER')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot accept order', $user->getId()));
        }

        $order = $data;

        // Order MUST have status = WAITING
        if ($order->getStatus() !== Order::STATUS_WAITING) {

            // Make sure order is not in the Redis queue anymore
            // This MAY happen if some user accepted the order and has been disconnected from the WebSocket server
            $this->redis->lrem('orders:waiting', 0, $order->getId());

            throw new BadRequestHttpException(sprintf('Order #%d cannot be accepted anymore', $order->getId()));
        }

        $order->setCourier($user);
        $order->setStatus(Order::STATUS_ACCEPTED);

        $this->redis->lrem('orders:dispatching', 0, $order->getId());
        $this->redis->hset('orders:delivering', 'order:'.$order->getId(), 'courier:'.$user->getId());

        $this->redis->publish('couriers', $user->getId());

        return $order;
    }
}