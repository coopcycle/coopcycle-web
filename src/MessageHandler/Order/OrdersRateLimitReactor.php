<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderDelayed;
use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Utils\OrdersRateLimit;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: -20)]
class OrdersRateLimitReactor
{

    public function __construct(
        private OrdersRateLimit $ordersRateLimit
    )
    {}

    public function __invoke(OrderCreated|OrderDelayed|OrderPicked|OrderRefused|OrderCancelled $event)
    {
        $this->ordersRateLimit->handleEvent($event);
    }

}
