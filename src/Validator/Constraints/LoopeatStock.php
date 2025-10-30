<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class LoopeatStock extends Constraint
{
    public $message = 'loopeat.insufficient_stock';

    public $useOverridenQuantity;

    public function __construct(bool $useOverridenQuantity = false)
    {
        $this->useOverridenQuantity = $useOverridenQuantity;

        parent::__construct();
    }

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
