<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Order;

class OrderStatus
{
    private static $statusMap = array(
        Order::STATUS_WAITING => Order::STATUS_ACCEPTED,
        Order::STATUS_ACCEPTED => Order::STATUS_READY,
        Order::STATUS_READY => Order::STATUS_PICKED,
    );

    public static function getNext(Order $order)
    {
        return self::$statusMap[$order->getStatus()];
    }
}
