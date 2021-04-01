<?php

namespace AppBundle\Event;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Contracts\EventDispatcher\Event;

class ItemAddedEvent extends Event
{
    public const NAME = 'checkout.item_added';

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
