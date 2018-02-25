<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Delivery extends Constraint
{
    public $addressTooFarMessage = 'address.tooFar';
    public $restaurantClosedMessage = 'delivery.date.restaurantClosed';
    public $dateHasPassedMessage = 'delivery.date.hasPassed';
    public $dateTooSoonMessage = 'delivery.date.tooSoon';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
