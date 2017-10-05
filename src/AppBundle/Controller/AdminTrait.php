<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait AdminTrait
{
    protected function restaurantOrders($id, array $routes = [])
    {
        $restaurantRepo = $this->getDoctrine()->getRepository(Restaurant::class);
        $orderRepo = $this->getDoctrine()->getRepository(Order::class);

        $restaurant = $restaurantRepo->find($id);

        $this->checkAccess($restaurant);
        $orders = $orderRepo->getWaitingOrdersForRestaurant($restaurant);

        $ordersJson = [];
        foreach ($orders as $order) {
            $ordersJson[] = $this->get('serializer')->serialize($order, 'jsonld', ['groups' => ['order']]);
        }

        return [
            'restaurant' => $restaurant,
            'restaurant_json' => $this->get('serializer')->serialize($restaurant, 'jsonld'),
            'orders' => $orders,
            'orders_json' => '[' . implode(',', $ordersJson) . ']',
            'restaurants_route' => $routes['restaurants'],
            'restaurant_route' => $routes['restaurant'],
            'routes' => $routes
        ];
    }

    protected function acceptOrder($id, $route, array $params = [])
    {
        $order = $this->getDoctrine()->getRepository(Order::class)->find($id);

        $this->checkAccess($order->getRestaurant());

        $order->setStatus(Order::STATUS_ACCEPTED);
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
}
