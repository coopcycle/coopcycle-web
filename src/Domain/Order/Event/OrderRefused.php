<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class OrderRefused extends Event implements DomainEvent, HasIconInterface
{
    private $reason;

    public static function messageName(): string
    {
        return 'order:refused';
    }

    public static function iconName()
    {
        return 'times';
    }

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
}
