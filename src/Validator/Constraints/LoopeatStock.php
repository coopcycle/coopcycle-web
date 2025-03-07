<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class LoopeatStock extends Constraint
{
    public $message = 'loopeat.insufficient_stock';

    public $useOverridenQuantity;

    public function __construct(bool $useOverridenQuantity = false)
    {
        $this->useOverridenQuantity = $useOverridenQuantity;

        parent::__construct();
    }

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
