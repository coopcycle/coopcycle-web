<?php

namespace AppBundle\Domain\Order;

use AppBundle\Sylius\Order\OrderInterface;
use SimpleBus\Message\Name\NamedMessage;

abstract class Event implements NamedMessage
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

    public function toPayload()
    {
        return [];
    }
}
