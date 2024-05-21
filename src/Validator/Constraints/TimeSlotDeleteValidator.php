<?php

namespace AppBundle\Validator\Constraints;


use AppBundle\Entity\TimeSlot;
use AppBundle\Serializer\ApplicationsNormalizer;
use AppBundle\Service\TimeSlotManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;


class TimeSlotDeleteValidator extends ConstraintValidator
{
    public function __construct(
        protected TimeSlotManager $timeSlotManager,
        protected ApplicationsNormalizer $normalizer
    ) {}

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof TimeSlot) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', TimeSlot::class));
        }

        $relatedEntities = $this->timeSlotManager->getTimeSlotApplications($object);

        if (count($relatedEntities) > 0) {
            $this->context
                ->buildViolation(
                    json_encode(array_map(
                        function ($entity) {return $this->normalizer->normalize($entity);},
                        $relatedEntities
                    ))
                )
                ->atPath('error')
                ->addViolation();
        }
    }
}
