<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\ApiUser;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Model\Order as BaseOrder;

class Order extends BaseOrder implements OrderInterface
{
    protected $customer;

    public function getCustomer()
    {
        return $this->customer;
    }

    public function setCustomer(ApiUser $customer)
    {
        $this->customer = $customer;

        return $this;
    }

    public function getTaxTotal(): int
    {
        $taxTotal = 0;

        foreach ($this->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            $taxTotal += $taxAdjustment->getAmount();
        }
        foreach ($this->items as $item) {
            $taxTotal += $item->getTaxTotal();
        }

        return $taxTotal;
    }
}
