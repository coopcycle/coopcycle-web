<?php

namespace AppBundle\Sylius\Order;

use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;

interface AdjustmentInterface extends BaseAdjustmentInterface
{
    public const TAX_ADJUSTMENT = 'tax';
}
