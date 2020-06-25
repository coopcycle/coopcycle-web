<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Exception\TimeRange\EmptyRangeException;
use AppBundle\Exception\TimeRange\NoWeekdayException;
use AppBundle\Utils\TimeRange;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class TimeRangeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        try {
            $timeRange = TimeRange::create($value);
        } catch (EmptyRangeException $e) {
            $this->context
                ->buildViolation($constraint->emptyRangeMessage)
                ->addViolation();
        } catch (NoWeekdayException $e) {
            $this->context
                ->buildViolation($constraint->noWeekdayMessage)
                ->addViolation();
        }
    }
}
