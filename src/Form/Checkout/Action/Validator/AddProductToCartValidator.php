<?php

namespace AppBundle\Form\Checkout\Action\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class AddProductToCartValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$value->product->isEnabled()) {
            $this->context
                ->buildViolation($constraint->productDisabled)
                ->atPath('items')
                ->setParameter('%code%', $value->product->getCode())
                ->addViolation();

            return;
        }

        if (!$value->restaurant->hasProduct($value->product)) {
            $this->context
                ->buildViolation($constraint->productNotBelongsTo)
                ->atPath('restaurant')
                ->setParameter('%code%', $value->product->getCode())
                ->addViolation();

            return;
        }

        if ($value->cart->getRestaurant() !== $value->restaurant && !$value->clear) {
            $this->context
                ->buildViolation($constraint->notSameRestaurant)
                ->atPath('restaurant')
                ->addViolation();

            return;
        }
    }
}
