<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use FOS\UserBundle\Model\UserInterface;
use Webmozart\Assert\Assert;

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

        $customer = $order->getCustomer();

        Assert::isInstanceOf($customer, CustomerInterface::class);

        $isCustomer = $customer->hasUser() && $user === $customer->getUser();

        return $isAdmin || $ownsRestaurant || $isCustomer;
    }

    public static function restaurant(UserInterface $user, LocalBusiness $restaurant)
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
