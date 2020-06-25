<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class LoopEatOrder extends Constraint
{
    public $insufficientQuantity = 'loopeat.insufficient_quantity';
    public $disabled = 'loopeat.disabled';
    public $insufficientBalance = 'loopeat.insufficient_balance';
    public $requestFailed = 'loopeat.request_failed';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
