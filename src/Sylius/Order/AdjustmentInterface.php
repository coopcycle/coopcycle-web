<?php

namespace AppBundle\Sylius\Order;

use Sylius\Component\Order\Model\AdjustmentInterface as BaseAdjustmentInterface;

interface AdjustmentInterface extends BaseAdjustmentInterface
{
    public const TAX_ADJUSTMENT = 'tax';
    public const DELIVERY_ADJUSTMENT = 'delivery';
    public const MENU_ITEM_MODIFIER_ADJUSTMENT = 'menu_item_modifier';
    public const ORDER_ITEM_PACKAGE_DELIVERY_CALCULATED_ADJUSTMENT = 'order_item_package_delivery_calculated';
    public const ORDER_ITEM_PACKAGE_DELIVERY_MANUAL_SUPPLEMENT_ADJUSTMENT = 'order_item_package_delivery_manual_supplement';
    public const FEE_ADJUSTMENT = 'fee';
    public const STRIPE_FEE_ADJUSTMENT = 'stripe_fee';
    public const DELIVERY_PROMOTION_ADJUSTMENT = 'delivery_promotion';
    public const ORDER_PROMOTION_ADJUSTMENT = 'order_promotion';
    public const REUSABLE_PACKAGING_ADJUSTMENT = 'reusable_packaging';
    public const TIP_ADJUSTMENT = 'tip';
    public const TRANSFER_AMOUNT_ADJUSTMENT = 'transfer_amount';
    public const INCIDENT_ADJUSTMENT = 'incident';
}
