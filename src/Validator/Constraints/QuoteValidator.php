<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Quote;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class QuoteValidator extends ConstraintValidator
{
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof Quote) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', Quote::class));
        }

        $quote = $object;

        if (count($quote->getTasks()) < 2) {
            $this->context->buildViolation($constraint->unexpectedTaskCountMessage)
                 ->atPath('items')
                 ->addViolation();

            return;
        }

        $pickupBefore = $quote->getPickup()->getBefore();

        foreach ($quote->getTasks() as $task) {
            if ($task->isDropoff()) {
                // TODO Improve this validation, use whole timewindow
                if ($pickupBefore > $task->getBefore()) {
                    $this->context->buildViolation($constraint->pickupAfterDropoffMessage)
                         ->atPath('items')
                         ->addViolation();
                    return;
                }
            }
        }
    }
}
