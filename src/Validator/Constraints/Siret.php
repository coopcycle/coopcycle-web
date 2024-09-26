<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Siret extends Constraint
{
    public $headOfficeNumber = 'siret.head_office';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }
}

