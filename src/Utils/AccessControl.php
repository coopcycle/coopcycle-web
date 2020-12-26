<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use FOS\UserBundle\Model\UserInterface;

class AccessControl
{
    public static function delivery(UserInterface $user, Delivery $delivery)
    {
        $isAdmin = $user->hasRole('ROLE_ADMIN');
        $ownsStore = null !== $delivery->getStore() && $user->ownsStore($delivery->getStore());

        return $isAdmin || $ownsStore;
    }

    public static function store(UserInterface $user, Store $store)
    {
        $isAdmin = $user->hasRole('ROLE_ADMIN');
        $ownsStore = $user->ownsStore($store);

        return $isAdmin || $ownsStore;
    }
}
