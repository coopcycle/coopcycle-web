<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Cart extends Constraint
{
    public $dateTooSoonMessage = 'cart.date.tooSoon';
    public $addressTooFarMessage = 'address.tooFar';
    public $totalIncludingTaxTooLowMessage = 'order.totalIncludingTax.tooLow';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
