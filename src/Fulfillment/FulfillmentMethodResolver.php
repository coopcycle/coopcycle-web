<?php

declare(strict_types=1);

namespace AppBundle\Fulfillment;

use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Sylius\Order\OrderInterface;

class FulfillmentMethodResolver
{
    public function resolveForOrder(OrderInterface $order): ?FulfillmentMethod
    {
        $restaurants = $order->getRestaurants();

        if (count($restaurants) === 0) {

            // Vendors may not have been processed yet
            $restaurant = $order->getRestaurant();

            if (null !== $restaurant) {

                return $restaurant->getFulfillmentMethod(
                    $order->getFulfillmentMethod()
                );
            }

            return null;
        }

        $first = $restaurants->first();
        $target = count($restaurants) === 1 ? $first : $first->getHub();

        return $target->getFulfillmentMethod(
            $order->getFulfillmentMethod()
        );
    }
}
