<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ShippingAddress extends Constraint
{
    const ADDRESS_NOT_SET = 'Order::ADDRESS_NOT_SET';
    const ADDRESS_TOO_FAR = 'Order::ADDRESS_TOO_FAR';

    public $addressNotSetMessage = 'address.notSet';
    public $addressTooFarMessage = 'address.tooFar';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
