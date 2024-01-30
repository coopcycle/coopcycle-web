<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SpreadsheetValidator extends ConstraintValidator
{
    public function __construct(private array $parsers)
    {}

    public function validate($value, Constraint $constraint)
    {
        try {
            $this->parsers[$constraint->type]->preValidate($value);
        } catch (\Exception $e) {
            $this->context->buildViolation($e->getMessage())
                ->addViolation();
        }
    }
}

