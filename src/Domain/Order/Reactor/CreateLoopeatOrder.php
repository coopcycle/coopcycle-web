<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Entity\Task;
use AppBundle\Service\Geofencing;

class CreateLoopeatOrder
{
    public function __construct(private LoopEatClient $client)
    {}

	public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        if (!$order->isLoopeat()) {
            return;
        }

        $result = $this->client->createOrder($order);

        $order->setLoopeatOrderId($result['id']);
    }
}
