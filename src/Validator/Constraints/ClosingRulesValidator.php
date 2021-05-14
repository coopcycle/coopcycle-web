<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\ShippingDateFilter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

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
