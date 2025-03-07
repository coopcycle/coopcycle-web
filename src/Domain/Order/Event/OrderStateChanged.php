<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class OrderStateChanged extends Event implements DomainEvent
{
    public function __construct(OrderInterface $order, private Event $triggeredBy)
    {
        parent::__construct($order);
    }

    public static function messageName(): string
    {
        return 'order:state_changed';
    }

    public function toPayload()
    {
        return [
            'newState' => $this->getOrder()->getState(),
            'triggeredByEvent' => [
                'name' => $this->triggeredBy::messageName(),
                'data' => $this->triggeredBy->toPayload(),
            ],
        ];
    }
}
