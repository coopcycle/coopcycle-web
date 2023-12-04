<?php

namespace AppBundle\Sylius\Order;

use Dflydev\Base32\Crockford\Crockford;
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

        $number = Crockford::encode($order->getId());

        $order->setNumber($number);
    }
}
