<?php

namespace AppBundle\ReusablePackaging;

use AppBundle\Sylius\Order\OrderInterface;

/**
 * @see https://docs.sylius.com/en/1.5/book/products/inventory.html#orderinventoryoperator
 * @see https://github.com/Sylius/Sylius/blob/62610f52cbe54ede47202ea337de27a5bfdfa97d/src/Sylius/Component/Core/Inventory/Operator/OrderInventoryOperator.php
 */
final class InventoryOperator
{
    public function hold(OrderInterface $order): void
    {
        $restaurant = $order->getRestaurant();

        if (!$restaurant) {
            return;
        }

        foreach ($restaurant->getReusablePackagings() as $reusablePackaging) {

            if (!$reusablePackaging->isTracked()) {
                continue;
            }

            $units = $order->countReusablePackagingUnits();

            if ($units > 0) {
                $reusablePackaging->setOnHold($reusablePackaging->getOnHold() + $units);
            }
        }
    }

    public function release(OrderInterface $order): void
    {
        $restaurant = $order->getRestaurant();

        if (!$restaurant) {
            return;
        }

        foreach ($restaurant->getReusablePackagings() as $reusablePackaging) {

            if (!$reusablePackaging->isTracked()) {
                continue;
            }

            $units = $order->countReusablePackagingUnits();

            if ($units > 0) {
                $reusablePackaging->setOnHold($reusablePackaging->getOnHold() - $units);
            }
        }
    }
}
