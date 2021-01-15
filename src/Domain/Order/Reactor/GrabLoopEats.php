<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\LoopEat\OAuthCredentialsInterface;
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

        // Make sure we call "grab" only if "return" has succeeded
        if ($this->client->return($order->getCustomer(), $order->getReusablePackagingPledgeReturn())) {

            if ($this->client->grab($order->getCustomer(), $order->getRestaurant(), $order->getReusablePackagingQuantity())) {

                Assert::isInstanceOf($order->getCustomer(), CustomerInterface::class);
                Assert::isInstanceOf($order->getCustomer(), OAuthCredentialsInterface::class);

                // When this is a guest checkout, we clear the credentials after grabbing
                if (!$order->getCustomer()->hasUser()) {
                    $order->getCustomer()->clearLoopEatCredentials();
                }
            }
        }
    }
}
