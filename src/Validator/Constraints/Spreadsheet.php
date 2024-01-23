<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Spreadsheet extends Constraint
{
    public $typeNotEditable = 'task.type.notEditable';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }
}

