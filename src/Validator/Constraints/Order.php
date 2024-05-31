<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Order extends Constraint
{
    const SHIPPED_AT_NOT_EMPTY = 'Order::SHIPPED_AT_NOT_EMPTY';
    const CONTAINS_DISABLED_PRODUCT = 'Order::CONTAINS_DISABLED_PRODUCT';
    const FULFILMENT_METHOD_DISABLED = 'Order::FULFILMENT_METHOD_DISABLED';

    public $totalIncludingTaxTooLowMessage = 'order.totalIncludingTax.tooLow';
    public $shippedAtNotEmptyMessage = 'order.shippedAt.notEmpty';
    public $containsDisabledProductMessage = 'order.items.containsDisabledProduct';
    public $unexpectedAdjustmentsCount = 'order.adjustments.unexpectedCount';
    public $fulfillmentMethodDisabledMessage = 'order.shippedAt.notAvailable';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
