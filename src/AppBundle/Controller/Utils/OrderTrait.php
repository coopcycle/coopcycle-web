<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\StripePayment;
use AppBundle\Sylius\Order\OrderTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Stripe;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait OrderTrait
{
    abstract protected function getOrderList(Request $request);

    public function orderListAction(Request $request)
    {
        $response = new Response();

        $showCanceled = false;
        if ($request->query->has('show_canceled')) {
            $showCanceled = $request->query->getBoolean('show_canceled');
            $response->headers->setCookie(new Cookie('__show_canceled', $showCanceled ? 'on' : 'off'));
        } elseif ($request->cookies->has('__show_canceled')) {
            $showCanceled = $request->cookies->getBoolean('__show_canceled');
        }

        $routes = $request->attributes->get('routes');

        [ $orders, $pages, $page ] = $this->getOrderList($request);

        return $this->render($request->attributes->get('template'), [
            'orders' => $orders,
            'pages' => $pages,
            'page' => $page,
            'routes' => $request->attributes->get('routes'),
            'show_buttons' => true,
            'show_canceled' => $showCanceled,
        ], $response);
    }

    /**
     * @Route("/profile/orders/{id}.pdf", name="profile_order_invoice", requirements={"id" = "\d+"})
     */
    public function orderInvoiceAction($id, Request $request)
    {
        $order = $this->getDoctrine()
            ->getRepository(Order::class)
            ->find($id);

        $this->accessControl($order);

        $html = $this->renderView('AppBundle:Order:invoice.html.twig', [
            'order' => $order
        ]);

        return new Response($this->get('knp_snappy.pdf')->getOutputFromHtml($html), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function acceptOrderAction($restaurantId, $orderId, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($orderId);

        $this->accessControl($order->getRestaurant());

        $stateMachineFactory = $this->get('sm.factory');
        $settingsManager = $this->get('coopcycle.settings_manager');

        $apiKey = $settingsManager->get('stripe_secret_key');
        Stripe\Stripe::setApiKey($apiKey);

        $stripePayment = $this->getDoctrine()
            ->getRepository(StripePayment::class)
            ->findOneByOrder($order);

        $stripePaymentStateMachine = $stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);
        $orderStateMachine = $stateMachineFactory->get($order, OrderTransitions::GRAPH);

        try {

            $charge = Stripe\Charge::retrieve($stripePayment->getCharge());
            if (!$charge->captured) {
                $charge->capture();
            }
            $stripePaymentStateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);

            $orderStateMachine->apply(OrderTransitions::TRANSITION_ACCEPT);
            $this->get('sylius.manager.order')->flush();

        } catch (\Exception $e) {
            $stripePaymentStateMachine->apply(PaymentTransitions::TRANSITION_FAIL);
        } finally {
            $this->getDoctrine()->getManagerForClass(StripePayment::class)->flush();
        }

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function refuseOrderAction($restaurantId, $orderId, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($orderId);

        $this->accessControl($order->getRestaurant());

        $stateMachineFactory = $this->get('sm.factory');

        $orderStateMachine = $stateMachineFactory->get($order, OrderTransitions::GRAPH);

        $orderStateMachine->apply(OrderTransitions::TRANSITION_REFUSE);
        $this->get('sylius.manager.order')->flush();

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function readyOrderAction($restaurantId, $orderId, Request $request)
    {
        $order = $this->getDoctrine()->getRepository(Order::class)->find($orderId);

        $this->accessControl($order->getRestaurant());

        $order->setStatus(Order::STATUS_READY);
        $this->getDoctrine()->getManagerForClass(Order::class)->flush();

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    private function cancelOrderById($id)
    {
        $order = $this->getDoctrine()->getRepository(Order::class)->find($id);
        $this->accessControl($order->getRestaurant());

        $this->get('coopcycle.order_manager')->cancel($order);
        $this->getDoctrine()->getManagerForClass(Order::class)->flush();
    }

    public function cancelOrderFromDashboardAction($restaurantId, $orderId, Request $request)
    {
        $this->cancelOrderById($orderId);

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function cancelOrderAction($id, Request $request)
    {
        $this->cancelOrderById($id);

        return $this->redirectToRoute($request->attributes->get('redirect_route'));
    }
}
