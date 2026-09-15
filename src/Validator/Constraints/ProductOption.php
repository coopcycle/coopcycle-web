<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ProductOption extends Constraint
{
    public $rangeNotAllowed = 'product_option.valuesRange.notAllowed';
    public $duplicateProductLink = 'product_option.values.duplicateProductLink';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
