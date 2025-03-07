<?php

declare(strict_types=1);

namespace AppBundle\Fulfillment;

use AppBundle\Business\Context as BusinessContext;
use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use AppBundle\Sylius\Order\OrderInterface;

class FulfillmentMethodResolver
{
    public function __construct(private BusinessContext $businessContext)
    {}

    public function resolveForOrder(OrderInterface $order): ?FulfillmentMethod
    {
        $fulfillment = $order->getFulfillmentMethod();

        if ($this->businessContext->isActive()) {
            if ($order->isBusiness()) {
                $businessAccount = $order->getBusinessAccount();

                return $businessAccount->getBusinessRestaurantGroup()
                    ->getFulfillmentMethod($fulfillment);
            }
        }

        $restaurants = $order->getRestaurants();

        if (count($restaurants) === 0) {

            // Vendors may not have been processed yet
            $restaurant = $order->getRestaurant();

            if (null !== $restaurant) {

                return $restaurant->getFulfillmentMethod($fulfillment);
            }

            return null;
        }

        $first = $restaurants->first();
        $target = count($restaurants) === 1 ? $first : $first->getHub();

        return $target->getFulfillmentMethod($fulfillment);
    }
}
