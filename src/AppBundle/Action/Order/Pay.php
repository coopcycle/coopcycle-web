<?php

namespace AppBundle\Action\Order;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\StripePayment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Pay extends Base
{
    /**
     * @Route(
     *     name="order_pay",
     *     path="/orders/{id}/pay",
     *     defaults={"_api_resource_class"=Order::class, "_api_item_operation_name"="pay"}
     * )
     * @Method("PUT")
     */
    public function __invoke(Order $data, Request $request)
    {
        $user = $this->getUser();

        $order = $data;

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

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $stripePayment->setStripeToken($data['stripeToken']);

        $stateMachine = $this->stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_CREATE);

        $this->doctrine->getManagerForClass(StripePayment::class)->flush();

        if (PaymentInterface::STATE_FAILED === $stripePayment->getState()) {
            throw new BadRequestHttpException($stripePayment->getLastError());
        }

        return $order;
    }
}
