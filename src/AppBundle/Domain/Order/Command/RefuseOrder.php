<?php

namespace AppBundle\Domain\Order\Command;

use AppBundle\Sylius\Order\OrderInterface;

class RefuseOrder
{
    private $order;
    private $reason;

    public function __construct(OrderInterface $order, $reason = null)
    {
        $this->order = $order;
        $this->reason = $reason;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getReason()
    {
        return $this->reason;
    }
}
