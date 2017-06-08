<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Decline
{
    use ActionTrait;

    /**
     * @Route(
     *     name="delivery_decline",
     *     path="/deliveries/{id}/decline",
     *     defaults={"_api_resource_class"=Delivery::class, "_api_item_operation_name"="decline"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data, Request $request)
    {
        $user = $this->getUser();
        $order = $data;

        if ($order->getStatus() !== Order::STATUS_WAITING) {
            throw new BadRequestHttpException(sprintf('Order #%d cannot be declined anymore', $order->getId()));
        }

        // TODO
        // Make sure the order is actually dispatched to the authenticated user
        // Convert orders:dispatching to a hash

        $message = [
            'order' => $order->getId(),
            'courier' => $user->getId(),
        ];

        $this->redis->publish('orders:declined', json_encode($message));

        // TODO Record declined order in database

        return $order;
    }
}
