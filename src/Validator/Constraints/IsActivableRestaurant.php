<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class IsActivableRestaurant extends Constraint
{
    public $enabledMessage = 'restaurant.notActivable';
    public $nameMessage = 'restaurant.name.notBlank';
    public $telephoneMessage = 'restaurant.telephone.notBlank';
    public $openingHoursMessage = 'restaurant.openingHours.notBlank';
    public $contractMessage = 'restaurant.contract.notValid';
    public $stripeAccountMessage = 'restaurant.stripeAccount.notSet';
    public $mercadopagoAccountMessage = 'restaurant.mercadopagoAccount.notSet';
    public $menuMessage = 'restaurant.menu.notSet';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
