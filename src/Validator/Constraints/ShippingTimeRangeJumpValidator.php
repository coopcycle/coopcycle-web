<?php

namespace AppBundle\Validator\Constraints;

use Carbon\Carbon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ShippingTimeRangeJumpValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        [ $displayed, $calculated ] = $value;

        if (!Carbon::instance($displayed->getLower())->isSameDay(Carbon::instance($calculated->getLower()))) {
            $this->context->buildViolation($constraint->nextDayMessage)
                ->addViolation();
        }
    }
}
