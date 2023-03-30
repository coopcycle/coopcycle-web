<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Utils\OrdersRateLimit;

class OrdersRateLimitReactor
{

    public function __construct(
        private OrdersRateLimit $ordersRateLimit
    )
    {}

    public function __invoke(Event $event)
    {
        $this->ordersRateLimit->handleEvent($event);
    }

}
