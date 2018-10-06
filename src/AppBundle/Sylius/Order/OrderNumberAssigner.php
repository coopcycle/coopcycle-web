<?php

namespace AppBundle\Sylius\Order;

use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Order\Model\OrderInterface;

final class OrderNumberAssigner implements OrderNumberAssignerInterface
{
    /**
     * {@inheritdoc}
     */
    public function assignNumber(OrderInterface $order): void
    {
        if (null !== $order->getNumber()) {
            return;
        }

        $number = strtoupper(base_convert($order->getId(), 10, 36));

        $order->setNumber($number);
    }
}
