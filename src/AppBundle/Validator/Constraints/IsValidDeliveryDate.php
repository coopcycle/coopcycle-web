<?php


namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;


/**
 * Class DeliveryDateInFuture
 * @package AppBundle\Validator\Constraints
 *
 * @Annotation
 */
class IsValidDeliveryDate extends Constraint
{
    private $now = null;

    public $message = "Delivery date %date% is invalid";


    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     * @return \DateTime
     */
    public function getNow()
    {
        return $this->now;
    }

}