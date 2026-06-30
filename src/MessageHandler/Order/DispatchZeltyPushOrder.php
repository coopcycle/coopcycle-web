<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Message\Zelty\PushOrder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DispatchZeltyPushOrder
{
    public function __construct(private MessageBusInterface $messageBus)
    {}

    public function __invoke(OrderCreated $event): void
    {
        $order = $event->getOrder();
        $restaurant = $order->getRestaurant();

        if ($restaurant === null || empty($restaurant->getZeltyApiKey())) {
            return;
        }

        $this->messageBus->dispatch(new PushOrder($order->getId()));
    }
}
