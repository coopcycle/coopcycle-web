<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Task;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class TaskValidator extends ConstraintValidator
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof Task) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', Task::class));
        }

        $originalEntityData = $this->doctrine
            ->getManagerForClass(Task::class)
            ->getUnitOfWork()
            ->getOriginalEntityData($object);

        if (is_array($originalEntityData) and !empty($originalEntityData)) {
            if (($object->hasPrevious() || $object->hasNext()) && $originalEntityData['type'] !== $object->getType()) {
                $this->context->buildViolation($constraint->typeNotEditable)
                    ->atPath('type')
                    ->addViolation();
            }
        }
    }
}
