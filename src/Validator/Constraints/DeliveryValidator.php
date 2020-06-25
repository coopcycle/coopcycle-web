<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Delivery;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class DeliveryValidator extends ConstraintValidator
{
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof Delivery) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', Delivery::class));
        }

        $delivery = $object;

        if (count($delivery->getTasks()) !== 2) {
            $this->context->buildViolation($constraint->unexpectedTaskCountMessage)
                 ->atPath('items')
                 ->addViolation();

            return;
        }

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        // TODO Improve this validation, use whole timewindow
        if ($pickup->getDoneBefore() > $dropoff->getDoneBefore()) {
            $this->context->buildViolation($constraint->pickupAfterDropoffMessage)
                 ->atPath('items')
                 ->addViolation();
        }
    }
}
