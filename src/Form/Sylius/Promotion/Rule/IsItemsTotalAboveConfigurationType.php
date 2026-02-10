<?php

namespace AppBundle\Form\Sylius\Promotion\Rule;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Sylius\Bundle\PromotionBundle\Form\Type\Rule\ItemTotalConfigurationType;
use Sylius\Component\Currency\Context\CurrencyContextInterface;

class IsItemsTotalAboveConfigurationType extends AbstractType
{
    public function __construct(private CurrencyContextInterface $currencyContext)
    {}

    public function getParent(): string
    {
        return ItemTotalConfigurationType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('currency', strtoupper($this->currencyContext->getCurrencyCode()));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'sylius_promotion_rule_is_items_total_above_configuration';
    }
}
