<?php


namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Restaurant;
use Carbon\Carbon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsValidDeliveryDateValidator extends ConstraintValidator
{

    public function validate($object, Constraint $constraint)
    {
        $now = Carbon::now();

        if ($object->getDate() < $now->modify('+ '.(string)Restaurant::PREPARATION_AND_DELIVERY_DELAY.' minutes')) {
            $this->context->buildViolation($constraint->message)
                 ->setParameter('%date%', $object->getDate()->format('Y-m-d H:i:s'))
                 ->atPath('date')
                 ->addViolation();
        }
    }
}