<?php

namespace AppBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
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
    public $paygreenShopIdMessage = 'restaurant.paygreenShopId.notSet';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
