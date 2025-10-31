<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ShippingAddress extends Constraint
{
    const ADDRESS_NOT_SET = 'Order::ADDRESS_NOT_SET';
    const ADDRESS_TOO_FAR = 'Order::ADDRESS_TOO_FAR';

    public $addressNotSetMessage = 'address.notSet';
    public $addressTooFarMessage = 'address.tooFar';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
