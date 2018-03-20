<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Restaurant;
use AppBundle\Service\RoutingInterface;
use Carbon\Carbon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class CartValidator extends ConstraintValidator
{
    private $routing;

    public function __construct(RoutingInterface $routing)
    {
        $this->routing = $routing;
    }

    public function validate($object, Constraint $constraint)
    {
        $validator = $this->context->getValidator();

        $now = Carbon::now();

        if (null === $object->getAddress()) {

            $this->context->buildViolation($constraint->addressEmptyMessage)
                ->atPath('address')
                ->addViolation();

        } else {

            if ($object->getDate() < $now->modify(sprintf('+%d  minutes', Restaurant::PREPARATION_AND_DELIVERY_DELAY))) {
                $this->context->buildViolation($constraint->dateTooSoonMessage)
                     ->setParameter('%date%', $object->getDate()->format('Y-m-d H:i:s'))
                     ->atPath('date')
                     ->addViolation();
            }

            $maxDistance = $object->getRestaurant()->getMaxDistance();

            $distance = $this->routing->getDistance(
                $object->getRestaurant()->getAddress()->getGeo(),
                $object->getAddress()->getGeo()
            );

            $violations = $validator->validate($distance, new Assert\LessThan(['value' => $maxDistance]));
            if (count($violations) > 0) {
                $this->context->buildViolation($constraint->addressTooFarMessage)
                    ->atPath('address')
                    ->addViolation();
            }

            if (count($object->getItems()) > 0) {
                $minimumCartAmount = $object->getRestaurant()->getMinimumCartAmount();
                $violations = $validator->validate($object->getTotal(), new Assert\GreaterThanOrEqual(['value' => $minimumCartAmount]));
                if (count($violations) > 0) {
                    $this->context->buildViolation($constraint->totalIncludingTaxTooLowMessage)
                        ->setParameter('%minimum_amount%', $minimumCartAmount)
                        ->atPath('total')
                        ->addViolation();
                }
            }
        }

    }
}
