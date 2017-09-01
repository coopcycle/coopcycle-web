<?php

namespace AppBundle\Action\Order;

use AppBundle\Action\ActionTrait;
use AppBundle\Entity\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Pay
{
    use ActionTrait;

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

        // Order MUST have status = CREATED
        if ($order->getStatus() !== Order::STATUS_CREATED) {
            throw new BadRequestHttpException(sprintf('Order #%d cannot be paid anymore', $order->getId()));
        }

        // Make sure the customer paying the order is correct
        if ($order->getCustomer() !== $user) {
            throw new AccessDeniedHttpException();
        }

        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        if (!isset($data['stripeToken'])) {
            throw new BadRequestHttpException('Stripe token is missing');
        }

        try {
            $this->paymentService->createCharge($order, $data['stripeToken']);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Could not create charge', $e);
        }

        $order->setStatus(Order::STATUS_WAITING);

        try {
            $event = new GenericEvent($order);
            $this->eventDispatcher->dispatch('order.payment_success', $event);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Could not dispatch payment success event', $e);
        }

        return $order;
    }
}
