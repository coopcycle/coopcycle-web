<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\OpeningHours\SchemaDotOrgParser;
use Spatie\OpeningHours\Exceptions\OverlappingTimeRanges;
use Spatie\OpeningHours\OpeningHours;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class NotOverlappingOpeningHoursValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        try {

            $data = SchemaDotOrgParser::parseCollection($value);
            $data['overflow'] = true;

            $openingHours = OpeningHours::create($data);

        } catch (OverlappingTimeRanges $e) {

            $this->context
                ->buildViolation($e->getMessage())
                ->addViolation();
        }
    }
}
