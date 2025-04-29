<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\LoopEat\Client as LoopEatClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: -20)]
class FinishLoopeatOrder
{
    public function __construct(private LoopEatClient $client)
    {}

	public function __invoke(OrderFulfilled $event)
    {
        $order = $event->getOrder();

        if (!$order->isLoopeat()) {
            return;
        }

        $this->client->finishOrder($order);
    }
}


