<?php

namespace AppBundle\Form\Sylius\Promotion\Rule;

use Symfony\Component\Form\AbstractType;

class IsItemsTotalAboveConfigurationType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'sylius_promotion_rule_is_items_total_above_configuration';
    }
}
