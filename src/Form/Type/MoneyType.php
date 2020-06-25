<?php

namespace AppBundle\Form\Type;

use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Component\Form\Extension\Core\Type\MoneyType as BaseMoneyType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MoneyType extends BaseMoneyType
{
    private $currencyContext;

    public function __construct(CurrencyContextInterface $currencyContext)
    {
        $this->currencyContext = $currencyContext;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('divisor', 100);
        $resolver->setDefault('currency', strtoupper($this->currencyContext->getCurrencyCode()));
    }
}
