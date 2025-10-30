<?php

namespace AppBundle\Form\Checkout\Action\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class AddProductToCart extends Constraint
{
    public $productDisabled = 'Product %code% is not enabled';
    public $productNotBelongsTo = 'Unable to add product %code%';
    public $notSameRestaurant = 'Restaurant mismatch';

    public function validatedBy(): string
    {
        return get_class($this).'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
