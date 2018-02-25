<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Order;
use AppBundle\Service\RoutingInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class OrderValidator extends ConstraintValidator
{
    private function validateMinimumAmount($expected, $actual, $message)
    {
        $constraint = new Assert\GreaterThanOrEqual(['value' => $expected]);

        $violations = $this->context->getValidator()->validate($actual, $constraint);
        if (count($violations) > 0) {
            $this->context->buildViolation($message)
                ->setParameter('%minimum_amount%', $expected)
                ->atPath('totalIncludingTax')
                ->addViolation();
        }
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof Order) {
            throw new \InvalidArgumentException('$object should be an Order instance');
        }

        $minimumAmount = $object->getRestaurant()->getMinimumCartAmount();
        $totalIncludingTax = $object->getItemsTotal();

        $this->validateMinimumAmount($minimumAmount, $totalIncludingTax, $constraint->totalIncludingTaxTooLowMessage);
    }
}
