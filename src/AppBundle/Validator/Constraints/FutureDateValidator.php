<?php

namespace AppBundle\Validator\Constraints;

use Carbon\Carbon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class FutureDateValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        // WARNING
        // We use Carbon to be able to mock the date in Behat tests
        $now = Carbon::now();

        if ($value < $now) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}

