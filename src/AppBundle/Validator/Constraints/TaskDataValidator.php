<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Task;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Util\PropertyPath;
use Symfony\Component\Validator\Validation;

class TaskDataValidator extends ConstraintValidator
{
    public function validate($object, Constraint $constraint)
    {
        if (!$object instanceof Task) {
            throw new \InvalidArgumentException(sprintf('$object should be an instance of %s', Task::class));
        }

        $fieldGroup = $object->getTaskFieldGroup();

        if (!$fieldGroup) {
            return;
        }

        $data = $object->getData();

        $validator = Validation::createValidator();

        $collectionConstraint = [];
        foreach ($fieldGroup->getFields() as $field) {
            $constraints = [];
            if ($field->isRequired()) {
                $constraints[] = new Assert\NotBlank();
            }
            if ('number' === $field->getType()) {
                $constraints[] = new Assert\Type('numeric');
            }
            $collectionConstraint[$field->getName()] = $constraints;
        }

        $violations = $validator->validate($data, new Assert\Collection([
            'fields' => $collectionConstraint,
            'allowExtraFields' => true,
        ]));

        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->context
                    ->buildViolation($violation->getMessage())
                    ->atPath(PropertyPath::append('data', $violation->getPropertyPath()))
                    ->addViolation();
            }
        }
    }
}
