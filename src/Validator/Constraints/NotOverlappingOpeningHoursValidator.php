<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\OpeningHours\SchemaDotOrgParser;
use Spatie\OpeningHours\Exceptions\OverlappingTimeRanges;
use Spatie\OpeningHours\OpeningHours;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

class NotOverlappingOpeningHoursValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $validator = Validation::createValidator();

        $errors = $validator->validate($value, [
            new Assert\All([
                'constraints' => new TimeRange(),
            ]),
        ]);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->context
                    ->buildViolation($error->getMessage())
                    ->atPath($error->getPropertyPath())
                    ->addViolation();
            }

            return;
        }

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
