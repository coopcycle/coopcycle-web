<?php

namespace AppBundle\Validator\Constraints;

use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsOrderModifiableValidator extends ConstraintValidator
{
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof OrderInterface) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', OrderInterface::class));
        }

        $isModifiable = $object->getId() === null || $object->getState() === OrderInterface::STATE_CART;

        if (!$isModifiable) {
            $this->context->buildViolation($constraint->message)
                ->atPath('state')
                ->addViolation();
        }
    }
}
