<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Order extends Constraint
{
    const ADDRESS_TOO_FAR = 'Order::ADDRESS_TOO_FAR';
    const ADDRESS_NOT_SET = 'Order::ADDRESS_NOT_SET';
    const SHIPPED_AT_EXPIRED = 'Order::SHIPPED_AT_EXPIRED';
    const SHIPPED_AT_NOT_AVAILABLE = 'Order::SHIPPED_AT_NOT_AVAILABLE';
    const SHIPPED_AT_NOT_EMPTY = 'Order::SHIPPED_AT_NOT_EMPTY';
    const CONTAINS_DISABLED_PRODUCT = 'Order::CONTAINS_DISABLED_PRODUCT';

    public $totalIncludingTaxTooLowMessage = 'order.totalIncludingTax.tooLow';
    public $restaurantClosedMessage = 'delivery.date.restaurantClosed';
    public $addressTooFarMessage = 'address.tooFar';
    public $addressNotSetMessage = 'address.notSet';
    public $shippedAtExpiredMessage = 'order.shippedAt.expired';
    public $shippedAtNotAvailableMessage = 'order.shippedAt.notAvailable';
    public $shippedAtNotEmptyMessage = 'order.shippedAt.notEmpty';
    public $containsDisabledProductMessage = 'order.items.containsDisabledProduct';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
