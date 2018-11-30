<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class MinimumAmount extends Constraint
{
    public $message = 'order.totalIncludingTax.tooLow';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }
}
