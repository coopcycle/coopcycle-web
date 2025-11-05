<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class IsOrderModifiable extends Constraint
{
    public $message = 'order.state.notModifiable';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
