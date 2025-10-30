<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class Siret extends Constraint
{
    public $headOfficeNumber = 'siret.head_office';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }
}

