<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Spreadsheet extends Constraint
{
    public $type;

    public function __construct(string $type = null, array $options = null, array $groups = null, $payload = null)
    {
        parent::__construct($options ?? [], $groups, $payload);

        $this->type = $type;
    }

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }
}

