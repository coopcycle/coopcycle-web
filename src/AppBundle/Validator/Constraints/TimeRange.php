<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class TimeRange extends Constraint
{
    public $emptyRangeMessage = 'time_range.empty';
    public $noWeekdayMessage = 'time_range.no_weekday';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
