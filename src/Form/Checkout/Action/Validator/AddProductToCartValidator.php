<?php

namespace AppBundle\Form\Checkout\Action\Validator;

use AppBundle\Sylius\Cart\RestaurantResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\LogicException;

class AddProductToCartValidator extends ConstraintValidator
{
    private $resolver;

    public function __construct(RestaurantResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function validate($value, Constraint $constraint)
    {
        $restaurant = $this->resolver->resolve();

        if (null === $restaurant) {
            throw new LogicException('No restaurant could be resolved from request.');
        }

        if (!$value->product->isEnabled()) {
            $this->context
                ->buildViolation($constraint->productDisabled)
                ->atPath('items')
                ->setParameter('%code%', $value->product->getCode())
                ->addViolation();

            return;
        }

        if (!$restaurant->hasProduct($value->product)) {
            $this->context
                ->buildViolation($constraint->productNotBelongsTo)
                ->atPath('restaurant')
                ->setParameter('%code%', $value->product->getCode())
                ->addViolation();

            return;
        }

        if (!$this->resolver->accept($value->cart) && !$value->clear) {
            $this->context
                ->buildViolation($constraint->notSameRestaurant)
                ->atPath('restaurant')
                ->addViolation();

            return;
        }
    }
}
