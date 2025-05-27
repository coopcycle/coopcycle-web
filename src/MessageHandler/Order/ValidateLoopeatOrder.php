<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\LoopEat\Client as LoopEatClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: -20)]
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

