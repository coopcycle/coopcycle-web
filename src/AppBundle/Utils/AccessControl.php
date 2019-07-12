<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Store;
use AppBundle\Sylius\Order\OrderInterface;
use FOS\UserBundle\Model\UserInterface;

class AccessControl
{
    public static function delivery(UserInterface $user, Delivery $delivery)
    {
        $isAdmin = $user->hasRole('ROLE_ADMIN');
        $ownsStore = null !== $delivery->getStore() && $user->ownsStore($delivery->getStore());

        return $isAdmin || $ownsStore;
    }

    public static function order(UserInterface $user, OrderInterface $order)
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

    public static function store(UserInterface $user, Store $store)
    {
        $isAdmin = $user->hasRole('ROLE_ADMIN');
        $ownsStore = $user->ownsStore($store);

        return $isAdmin || $ownsStore;
    }
}
