<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class OrderPriceUpdated extends Event implements DomainEvent, HasIconInterface
{

    public function __construct(
        OrderInterface $order,
        private readonly int $newTotal,
        private readonly int $newTaxTotal,
        private readonly int $oldTotal,
        private readonly int $oldTaxTotal
    )
    {
        parent::__construct($order);
    }

    public function toPayload()
    {
        return [
            'new_total' => $this->newTotal,
            'new_tax_total' => $this->newTaxTotal,
            'old_total' => $this->oldTotal,
            'old_tax_total' => $this->oldTaxTotal,
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
