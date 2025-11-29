<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TimeRange extends Constraint
{
    public $emptyRangeMessage = 'time_range.empty';
    public $noWeekdayMessage = 'time_range.no_weekday';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
