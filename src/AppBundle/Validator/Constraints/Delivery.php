<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Delivery extends Constraint
{
    public $unexpectedTaskCountMessage = 'delivery.tasks.unexpectedCount';
    public $pickupAfterDropoffMessage = 'delivery.tasks.pickupAfterDropoff';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
