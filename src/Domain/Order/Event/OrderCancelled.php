<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class OrderCancelled extends Event implements DomainEvent
{
    private $reason;

    public function __construct(OrderInterface $order, $reason = null)
    {
        parent::__construct($order);

        $this->reason = $reason;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function toPayload()
    {
        return [
            'reason' => $this->getReason(),
        ];
    }

    public static function messageName(): string
    {
        return 'order:cancelled';
    }
}
