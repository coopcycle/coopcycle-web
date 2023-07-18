<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Entity\Task;
use AppBundle\Service\Geofencing;

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


