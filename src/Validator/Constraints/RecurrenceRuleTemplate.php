<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class RecurrenceRuleTemplate extends Constraint
{
    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
