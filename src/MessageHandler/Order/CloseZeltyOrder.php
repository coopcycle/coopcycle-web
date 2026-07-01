<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Integration\Zelty\ZeltyClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class CloseZeltyOrder
{
    public function __construct(private readonly ZeltyClient $zeltyClient) {}

    public function __invoke(OrderFulfilled $event): void
    {
        $order = $event->getOrder();

        $zeltyOrderId = $order->getZeltyOrderId();
        if ($zeltyOrderId === null) {
            return;
        }

        $restaurant = $order->getRestaurant();
        if ($restaurant === null || empty($restaurant->getZeltyApiKey())) {
            return;
        }

        $this->zeltyClient->setAuth($restaurant->getZeltyApiKey());
        $this->zeltyClient->closeOrder($zeltyOrderId);
    }
}
