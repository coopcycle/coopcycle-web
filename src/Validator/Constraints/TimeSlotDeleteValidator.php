<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\TimeSlot;
use AppBundle\Service\TimeSlotManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class TimeSlotDeleteValidator extends ConstraintValidator
{
    public function __construct(protected TimeSlotManager $timeSlotManager)
    {}

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof TimeSlot) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', TimeSlot::class));
        }

        $relatedEntities = $this->timeSlotManager->getTimeSlotApplications($object);

        if (count($relatedEntities) > 0) {
            foreach ($relatedEntities as $entity) {
                $this->context
                    ->buildViolation(
                        sprintf('%s is used by %s#%d', get_class($object), get_class($entity), $entity->getId())
                    )
                    ->addViolation();
            }
        }
    }
}
