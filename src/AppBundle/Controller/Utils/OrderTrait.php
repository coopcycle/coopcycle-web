<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\DeliveryOrderItem;
use AppBundle\Entity\Order;
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
        $order = $this->getDoctrine()->getRepository(Order::class)->find($orderId);
        $this->accessControl($order->getRestaurant());

        $this->get('order.manager')->accept($order);
        $this->getDoctrine()->getManagerForClass(Order::class)->flush();

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function refuseOrderAction($restaurantId, $orderId, Request $request)
    {
        $order = $this->getDoctrine()->getRepository(Order::class)->find($orderId);

        $this->accessControl($order->getRestaurant());

        $order->setStatus(Order::STATUS_REFUSED);
        $this->getDoctrine()->getManagerForClass(Order::class)->flush();

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

        $this->get('order.manager')->cancel($order);
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

    protected function normalizeOrders(array $syliusOrders, array $coopcycleOrders)
    {
        $syliusOrders = array_map(function ($order) {

            $delivery = $this->getDoctrine()
                ->getRepository(DeliveryOrderItem::class)
                ->findOneByOrderItem($order->getItems()->get(0))
                ->getDelivery();

            return [
                'id' => $order->getId(),
                'customer' => [
                    'username' => $order->getCustomer()->getUsername(),
                ],
                'createdAt' => $order->getCreatedAt(),
                'state' => $order->getState(),
                'total' => $order->getTotal(),
                'delivery' => $delivery,
            ];

        }, $syliusOrders);

        $orders = array_merge($coopcycleOrders, $syliusOrders);

        usort($orders, function ($a, $b) {
            $createdAtA = is_array($a) ? $a['createdAt'] : $a->getCreatedAt();
            $createdAtB = is_array($b) ? $b['createdAt'] : $b->getCreatedAt();
            return $createdAtA < $createdAtB ? 1 : -1;
        });

        return $orders;
    }
}
