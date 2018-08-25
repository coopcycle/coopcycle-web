<?php

namespace AppBundle\Domain\Order;

use AppBundle\Domain\Event as BaseEvent;
use AppBundle\Sylius\Order\OrderInterface;
use SimpleBus\Message\Name\NamedMessage;

abstract class Event extends BaseEvent
{
    protected $order;

    public function __construct(OrderInterface $order)
    {
        $this->order = $order;
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }
}
