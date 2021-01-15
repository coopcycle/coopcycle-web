<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Sylius\Product\ProductOptionInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProductOptionValidator extends ConstraintValidator
{
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof ProductOptionInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', ProductOptionInterface::class));
        }

        if (!$object->isAdditional() && null !== $object->getValuesRange()) {
            $this->context->buildViolation($constraint->rangeNotAllowed)
                ->atPath('valuesRange')
                ->addViolation();
        }
    }
}
