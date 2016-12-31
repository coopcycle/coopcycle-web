<?php

namespace AppBundle\Action;

use AppBundle\Entity\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OrderPay
{
    use OrderActionTrait;

    /**
     * @Route(
     *     name="order_pay",
     *     path="/orders/{id}/pay",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="pay"}
     * )
     * @Method("PUT")
     */
    public function __invoke($data, Request $request)
    {
        $user = $this->getUser();

        $order = $data;

        // Make sure the customer paying the order is correct
        if ($order->getCustomer() !== $user) {
            throw new AccessDeniedException();
        }

        $this->paymentService->createCharge($order, $request);

        $order->setStatus(Order::STATUS_WAITING);

        $event = new GenericEvent($order);
        $this->eventDispatcher->dispatch('order.payment_success', $event);

        return $order;
    }
}