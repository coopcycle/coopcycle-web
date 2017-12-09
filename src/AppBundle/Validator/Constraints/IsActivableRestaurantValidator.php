<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Restaurant;
use Carbon\Carbon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Validation;

class IsActivableRestaurantValidator extends ConstraintValidator
{
    public function validate($object, Constraint $constraint)
    {
        $validator = $this->context->getValidator();

        $nameErrors = $validator->validate($object->getName(), new Assert\NotBlank());
        if (count($nameErrors) > 0) {
            $this->context->buildViolation($constraint->nameMessage)
                ->atPath('name')
                ->addViolation();
        }

        $telephoneErrors = $validator->validate($object->getTelephone(), new Assert\NotBlank());
        if (count($telephoneErrors) > 0) {
            $this->context->buildViolation($constraint->telephoneMessage)
                ->atPath('telephone')
                ->addViolation();
        }

        $openingHoursErrors = $validator->validate($object->getOpeningHours(), new Assert\NotBlank());
        if (count($openingHoursErrors) > 0) {
            $this->context->buildViolation($constraint->openingHoursMessage)
                ->atPath('openingHours')
                ->addViolation();
        }

        $contractErrors = $validator->validate($object->getContract(), [
            new Assert\NotBlank(),
            new Assert\Valid(),
        ]);
        if (count($contractErrors) > 0) {
            $this->context->buildViolation($constraint->contractMessage)
                ->atPath('contract')
                ->addViolation();
        }

        $hasErrors = count($nameErrors) > 0 || count($telephoneErrors) > 0 || count($openingHoursErrors);

        if ($hasErrors) {
            $this->context->buildViolation($constraint->enabledMessage)
                ->atPath('enabled')
                ->addViolation();
        }
    }
}
