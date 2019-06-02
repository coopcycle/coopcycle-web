<?php

namespace AppBundle\Form\Sylius\Promotion\Rule;

use Symfony\Component\Form\AbstractType;

class IsCustomerConfigurationType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'sylius_promotion_rule_is_customer_configuration';
    }
}
