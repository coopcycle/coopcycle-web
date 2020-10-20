<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ShippingTimeRange extends Constraint
{
    const SHIPPED_AT_EXPIRED = 'Order::SHIPPED_AT_EXPIRED';
    const SHIPPED_AT_NOT_AVAILABLE = 'Order::SHIPPED_AT_NOT_AVAILABLE';

    public $shippedAtExpiredMessage = 'order.shippedAt.expired';
    public $shippedAtNotAvailableMessage = 'order.shippedAt.notAvailable';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
