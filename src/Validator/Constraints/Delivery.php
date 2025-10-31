<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class Delivery extends Constraint
{
    public $unexpectedTaskCountMessage = 'delivery.tasks.unexpectedCount';
    public $pickupAfterDropoffMessage = 'delivery.tasks.pickupAfterDropoff';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
