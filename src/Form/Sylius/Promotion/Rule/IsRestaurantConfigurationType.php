<?php

namespace AppBundle\Form\Sylius\Promotion\Rule;

use Symfony\Component\Form\AbstractType;

class IsRestaurantConfigurationType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'sylius_promotion_rule_is_restaurant_configuration';
    }
}
