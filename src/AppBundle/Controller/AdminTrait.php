<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait AdminTrait
{
    protected function restaurantDashboard($restaurantId, $orderId, Request $request, array $routes = [])
    {
        $restaurantRepository = $this->getDoctrine()->getRepository(Restaurant::class);
        $orderRepository      = $this->getDoctrine()->getRepository(Order::class);

        $restaurant = $restaurantRepository->find($restaurantId);

        $this->checkAccess($restaurant);

        $date = new \DateTime('now');
        if ($request->query->has('date')) {
            $date = new \DateTime($request->query->get('date'));
        }
        $date->modify('-1 day');

        $orders = $orderRepository->getWaitingOrdersForRestaurant($restaurant, $date);
        $ordersJson = array_map(function ($order) {
            return $this->get('serializer')->serialize($order, 'jsonld', ['groups' => ['order']]);
        }, $orders);

        $order = null;
        if ($orderId) {
            $order = $orderRepository->find($orderId);
        }

        return [
            'restaurant' => $restaurant,
            'restaurant_json' => $this->get('serializer')->serialize($restaurant, 'jsonld'),
            'orders' => $orders,
            'orders_json' => '[' . implode(',', $ordersJson) . ']', // FIXME This is ugly...
            'order' => $order,
            'order_json' => $this->get('serializer')->serialize($order, 'jsonld', ['groups' => ['order']]),
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
            'routes' => $routes,
            'date' => $date,
        ];
    }

    protected function acceptOrder($id, $route, array $params = [])
    {
        $order = $this->getDoctrine()->getRepository(Order::class)->find($id);
        $this->checkAccess($order->getRestaurant());

        $this->get('order.manager')->accept($order);
        $this->getDoctrine()->getManagerForClass(Order::class)->flush();

        return $this->redirectToRoute($route, $params);
    }

    protected function refuseOrder($id, $route, array $params = [])
    {
        $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

        $this->checkAccess($order->getRestaurant());

        $order->setStatus(Order::STATUS_REFUSED);
        $this->getDoctrine()->getManagerForClass(Order::class)->flush();

        return $this->redirectToRoute($route, $params);
    }

    protected function readyOrder($id, $route, array $params = [])
    {
        $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

        $this->checkAccess($order->getRestaurant());

        $order->setStatus(Order::STATUS_READY);
        $this->getDoctrine()->getManagerForClass(Order::class)->flush();

        return $this->redirectToRoute($route, $params);
    }

    protected function cancelOrder($id, $route, array $params = [])
    {
        $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

        $this->checkAccess($order->getRestaurant());

        $order->setStatus(Order::STATUS_CANCELED);
        $order->getDelivery()->setStatus(Order::STATUS_CANCELED);

        $this->getDoctrine()->getManagerForClass(Order::class)->flush();
        $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

        $this->get('snc_redis.default')->lrem('deliveries:waiting', 0, $order->getDelivery()->getId());

        return $this->redirectToRoute($route, $params);
    }

    protected function invoiceAsPdfAction(Order $order)
    {
        $html = $this->renderView('AppBundle:Order:invoice.html.twig', [
            'order' => $order
        ]);

        return new Response($this->get('knp_snappy.pdf')->getOutputFromHtml($html), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function checkOrderAccess(Order $order)
    {
        $isAdmin = $this->getUser()->hasRole('ROLE_ADMIN');
        $ownsRestaurant = $this->getUser()->ownsRestaurant($order->getRestaurant());
        $isCustomer = $this->getUser() === $order->getCustomer();

        if ($isAdmin || $ownsRestaurant || $isCustomer) {
            return;
        }

        throw new AccessDeniedHttpException();
    }
}
