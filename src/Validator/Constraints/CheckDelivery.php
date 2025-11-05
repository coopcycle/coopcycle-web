<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class CheckDelivery extends Constraint
{
    public $notValidMessage = 'delivery.check.notValid';
    public $noStoreMessage = 'delivery.check.noStore';
    public $outOfBoundsMessage = 'delivery.check.outOfBounds';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
