<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ClosingRulesValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        foreach ($constraint->closingRules as $closingRule) {

            if ($closingRule->getEndDate() <= $value->getLower()) {
                continue;
            }

            if ($value->getUpper() > $closingRule->getStartDate()) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();

                return;
            }
        }
    }
}
