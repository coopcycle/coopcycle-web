<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Domain\Order\Event;

class GrabLoopEats
{
    private $client;

    public function __construct(LoopEatClient $client)
    {
        $this->client = $client;
    }

    public function __invoke(Event\OrderPicked $event)
    {
        $order = $event->getOrder();
        $restaurant = $order->getRestaurant();

        if (null === $restaurant) {
            return;
        }

        if (!$restaurant->isLoopeatEnabled()) {
            return;
        }

        if (!$order->isReusablePackagingEnabled() || $order->getReusablePackagingQuantity() < 1) {
            return;
        }

        // TODO Make sure the reusable packagings are actually from LoopEat

        $this->client->return($order->getCustomer(), $order->getReusablePackagingPledgeReturn());
        $this->client->grab($order->getCustomer(), $order->getRestaurant(), $order->getReusablePackagingQuantity());
    }
}
