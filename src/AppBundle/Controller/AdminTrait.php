<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;

trait AdminTrait
{
    private function restaurantOrders($id)
    {
        $restaurantRepo = $this->getDoctrine()->getRepository(Restaurant::class);
        $orderRepo = $this->getDoctrine()->getRepository(Order::class);

        $restaurant = $restaurantRepo->find($id);
        $orders = $orderRepo->getWaitingOrders();

        $this->checkAccess($restaurant);

        $ordersJson = [];
        foreach ($orders as $order) {
            $ordersJson[] = $this->get('serializer')->serialize($order, 'jsonld', ['groups' => ['order']]);
        }

        return [
            'restaurant' => $restaurant,
            'restaurant_json' => $this->get('serializer')->serialize($restaurant, 'jsonld'),
            'orders' => $orders,
            'orders_json' => '[' . implode(',', $ordersJson) . ']',
            'restaurants_route' => 'admin_restaurants',
            'restaurant_route' => 'admin_restaurant',
        ];
    }
}
