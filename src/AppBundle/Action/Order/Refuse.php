<?php

namespace AppBundle\Action\Order;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Refuse
{
    use ActionTrait;

    /**
     * @Route(
     *     name="order_refuse",
     *     path="/orders/{id}/refuse",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="refuse"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data)
    {
        $user = $this->getUser();

        // Only restaurants can refuse orders
        if (!$user->hasRole('ROLE_RESTAURANT')) {
            throw new AccessDeniedHttpException(sprintf('User #%d cannot refuse order', $user->getId()));
        }

        $order = $data;

        // Order MUST have status = WAITING
        if ($order->getStatus() !== Order::STATUS_WAITING) {
            throw new BadRequestHttpException(sprintf('Order #%d cannot be refused anymore', $order->getId()));
        }

        $order->setStatus(Order::STATUS_REFUSED);

        return $order;
    }
}
