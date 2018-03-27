<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use Sylius\Component\Order\Model\OrderItem as BaseOrderItem;

class OrderItem extends BaseOrderItem implements OrderItemInterface
{
    public function getTaxTotal(): int
    {
        $taxTotal = 0;

        foreach ($this->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            $taxTotal += $taxAdjustment->getAmount();
        }

        return $taxTotal;
    }
}
