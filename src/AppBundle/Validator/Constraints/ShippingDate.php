<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ShippingDate extends Constraint
{
    public $message = 'order.shippedAt.notAvailable';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }
}
