<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class CheckDelivery extends Constraint
{
    public $notValidMessage = 'delivery.check.notValid';
    public $noStoreMessage = 'delivery.check.noStore';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
