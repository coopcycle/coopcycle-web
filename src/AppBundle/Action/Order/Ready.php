<?php

namespace AppBundle\Action\Order;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Ready
{
    use ActionTrait;

    /**
     * @Route(
     *     name="order_ready",
     *     path="/orders/{id}/ready",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="ready"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $user = $this->getUser();

        // Only restaurants can set orders as ready
        if (!$user->hasRole('ROLE_RESTAURANT')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot set order to ready', $user->getId()));
        }

        $order = $data;

        if (!$this->getUser()->ownsRestaurant($order->getRestaurant())) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot set order to ready', $user->getId()));
        }

        // Order MUST have status = ACCEPTED
        if ($order->getStatus() !== Order::STATUS_ACCEPTED) {
            throw new BadRequestHttpException(sprintf('Order #%d cannot be set to ready anymore', $order->getId()));
        }

        $order->setStatus(Order::STATUS_READY);

        return $order;
    }
}
