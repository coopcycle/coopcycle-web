<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class FutureDate extends Constraint
{
    public $message = 'order.shippedAt.expired';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }
}
