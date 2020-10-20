<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\ShippingDateFilter;
use Carbon\Carbon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ShippingTimeRangeValidator extends ConstraintValidator
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
            throw new UnexpectedValueException($object, OrderInterface::class);
        }

        // WARNING
        // We use Carbon to be able to mock the date in Behat tests
        $now = Carbon::now();

        $isNew = $object->getId() === null || $object->getState() === OrderInterface::STATE_CART;

        // A new order with empty time range is valid
        if ($isNew && null === $value) {
            return;
        }

        if ($isNew) {

            if (null !== $value) {
                if ($value->getLower() < $now) {
                    $this->context
                        ->buildViolation($constraint->shippedAtExpiredMessage)
                        ->setCode(ShippingTimeRange::SHIPPED_AT_EXPIRED)
                        ->addViolation();

                    return;
                }
            }

            $restaurant = $object->getRestaurant();

            if (null !== $value && null !== $restaurant && $restaurant->getOpeningHoursBehavior() === 'asap') {
                if (false === $this->shippingDateFilter->accept($object, $value->getLower(), $now)) {
                    $this->context->buildViolation($constraint->shippedAtNotAvailableMessage)
                        ->setCode(ShippingTimeRange::SHIPPED_AT_NOT_AVAILABLE)
                        ->addViolation();

                    return;
                }
            }
        }
    }
}
