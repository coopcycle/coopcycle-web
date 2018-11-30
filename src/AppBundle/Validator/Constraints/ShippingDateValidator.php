<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\ShippingDateFilter;
use Carbon\Carbon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ShippingDateValidator extends ConstraintValidator
{
    private $shippingDateFilter;

    public function __construct(ShippingDateFilter $shippingDateFilter)
    {
        $this->shippingDateFilter = $shippingDateFilter;
    }

    public function validate($value, Constraint $constraint)
    {
        $object = $this->context->getObject();

        if (null === $object || !$object instanceof OrderInterface) {

            return;
        }

        if (null === $object->getRestaurant()) {

            return;
        }

        // WARNING
        // We use Carbon to be able to mock the date in Behat tests
        $now = Carbon::now();

        if (false === $this->shippingDateFilter->accept($object, $value, $now)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();

            return;
        }
    }
}

