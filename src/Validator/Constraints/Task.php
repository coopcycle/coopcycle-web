<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Task extends Constraint
{
    const TYPE_NOT_EDITABLE = 'Task::TYPE_NOT_EDITABLE';

    public $typeNotEditable = 'task.type.notEditable';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
