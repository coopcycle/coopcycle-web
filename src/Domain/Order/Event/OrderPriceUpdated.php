<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class OrderPriceUpdated extends Event implements DomainEvent, HasIconInterface
{

    public function __construct(
        OrderInterface  $order,
        private int     $new_price,
        private int     $old_price,
    )
    {
        parent::__construct($order);
    }

    public function getNewPrice(): int
    {
        return $this->new_price;
    }

    public function getOldPrice(): int
    {
        return $this->old_price;
    }

    public function toPayload()
    {
        return [
            'price'     => $this->getNewPrice(),
            'old_price' => $this->getOldPrice()
        ];
    }

    public static function iconName()
    {
        return 'calculator';
    }

    public static function messageName(): string
    {
        return 'order:price_updated';
    }
}
