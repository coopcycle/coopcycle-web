<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Entity\Task;
use AppBundle\Service\Geofencing;

class ValidateLoopeatOrder
{
    public function __construct(private LoopEatClient $client)
    {}

	public function __invoke(OrderPicked $event)
    {
        $order = $event->getOrder();

        if (!$order->isLoopeat()) {
            return;
        }

        $this->client->validateOrder($order);
    }
}

