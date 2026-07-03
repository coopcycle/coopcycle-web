<?php

namespace AppBundle\Form\Sylius\Promotion;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Sylius\Bundle\PromotionBundle\Form\Type\Action\FixedDiscountConfigurationType;
use Sylius\Component\Currency\Context\CurrencyContextInterface;

class CurrencyAwareFixedDiscountConfigurationType extends AbstractType
{
    public function __construct(private CurrencyContextInterface $currencyContext)
    {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('currency', strtoupper($this->currencyContext->getCurrencyCode()));
    }

    public function getParent(): string
    {
        return FixedDiscountConfigurationType::class;
    }
}
