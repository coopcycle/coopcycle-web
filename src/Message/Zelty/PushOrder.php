<?php

namespace AppBundle\Message\Zelty;

class PushOrder
{
    public function __construct(private int $orderId)
    {}

    public function getOrderId(): int
    {
        return $this->orderId;
    }
}
