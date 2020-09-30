<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Task\Group as TaskGroup;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class TaskGroupValidator extends ConstraintValidator
{
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof TaskGroup) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', TaskGroup::class));
        }

        if (null !== $object->getId()) {
            return;
        }

        $refs = [];
        foreach ($object->getTasks() as $index => $task) {

            $ref = $task->getRef();

            if (empty($ref)) {
                continue;
            }

            if (in_array($ref, $refs)) {
                $this->context->buildViolation($constraint->duplicateRef)
                    ->atPath(sprintf('tasks[%d]', $index))
                    ->setParameter('%ref%', $ref)
                    ->addViolation();
                break;
            }

            $refs[] = $ref;
        }
    }
}
