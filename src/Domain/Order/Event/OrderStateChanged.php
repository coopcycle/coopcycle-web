<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class OrderStateChanged extends Event implements DomainEvent, HasIconInterface
{
    public function __construct(OrderInterface $order, private Event $triggeredBy)
    {
        parent::__construct($order);
    }

    public static function messageName(): string
    {
        return 'order:state_changed';
    }

    public static function iconName()
    {
        return 'exchange';
    }

    public function getTriggeredBy(): Event
    {
        return $this->triggeredBy;
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
