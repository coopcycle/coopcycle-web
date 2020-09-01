<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Customer\CustomerInterface;
use Webmozart\Assert\Assert;

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

        $customer = $order->getCustomer();

        Assert::isInstanceOf($customer, CustomerInterface::class);

        if (!$customer->hasUser()) {
            return;
        }

        $this->client->return($customer->getUser(), $order->getReusablePackagingPledgeReturn());
        $this->client->grab($customer->getUser(), $order->getRestaurant(), $order->getReusablePackagingQuantity());
    }
}
