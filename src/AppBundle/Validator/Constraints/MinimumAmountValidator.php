<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Address;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Service\RoutingInterface;
use AppBundle\Utils\ShippingDateFilter;
use Carbon\Carbon;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class MinimumAmountValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $object = $this->context->getObject();

        if (null === $object || !$object instanceof OrderInterface) {

            return;
        }

        if (null === $object->getRestaurant()) {

            return;
        }

        $minimumAmount = $object->getRestaurant()->getMinimumCartAmount();
        $itemsTotal = $object->getItemsTotal();

        if ($itemsTotal < $minimumAmount) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('%minimum_amount%', number_format($minimumAmount / 100, 2))
                ->addViolation();
        }
    }
}
