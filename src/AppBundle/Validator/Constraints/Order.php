<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Order extends Constraint
{
    public $totalIncludingTaxTooLowMessage = 'order.totalIncludingTax.tooLow';
    public $restaurantClosedMessage = 'delivery.date.restaurantClosed';
    public $addressTooFarMessage = 'address.tooFar';
    public $addressNotSetMessage = 'address.notSet';
    public $shippedAtExpiredMessage = 'order.shippedAt.expired';
    public $shippedAtNotAvailableMessage = 'order.shippedAt.notAvailable';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
