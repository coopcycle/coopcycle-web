<?php

namespace AppBundle\Event;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Contracts\EventDispatcher\Event;

class ItemQuantityChangedEvent extends Event
{
    public const NAME = 'checkout.item_removed';

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
