<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Entity\Restaurant;
use FOS\UserBundle\Model\UserInterface;

class AccessControl
{
    public static function delivery(UserInterface $user, Delivery $delivery)
    {
        $isAdmin = $user->hasRole('ROLE_ADMIN');
        $isCourier = $user === $delivery->getCourier();

        return $isAdmin || $isCourier;
    }

    public static function order(UserInterface $user, Order $order)
    {
        $isAdmin = $user->hasRole('ROLE_ADMIN');
        $ownsRestaurant = $user->ownsRestaurant($order->getRestaurant());
        $isCustomer = $user === $order->getCustomer();

        return $isAdmin || $ownsRestaurant || $isCustomer;
    }

    public static function restaurant(UserInterface $user, Restaurant $restaurant)
    {
        $isAdmin = $user->hasRole('ROLE_ADMIN');
        $ownsRestaurant = $user->ownsRestaurant($restaurant);

        return $isAdmin || $ownsRestaurant;
    }
}
