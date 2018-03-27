<?php

namespace AppBundle\Sylius\Order;

use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

interface OrderInterface extends BaseOrderInterface
{
    /**
     * @return int
     */
    public function getTaxTotal(): int;
}
