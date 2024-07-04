<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class OrderFulfilled extends Event implements DomainEvent, HasIconInterface
{
    private $shouldCompletePayment = true;

    public function __construct(OrderInterface $order, bool $shouldCompletePayment = true)
    {
        parent::__construct($order);

        $this->shouldCompletePayment = $shouldCompletePayment;
    }

    public static function messageName(): string
    {
        return 'order:fulfilled';
    }

    public static function iconName()
    {
        return 'check';
    }

    public function shouldCompletePayment(): bool
    {
        return $this->shouldCompletePayment;
    }
}

