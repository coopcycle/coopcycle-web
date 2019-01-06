<?php

namespace AppBundle\Utils;

use Sylius\Bundle\CurrencyBundle\Templating\Helper\CurrencyHelperInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;

class PriceFormatter
{
    private $currencyContext;
    private $currencyHelper;

    public function __construct(
        CurrencyContextInterface $currencyContext,
        CurrencyHelperInterface $currencyHelper)
    {
        $this->currencyContext = $currencyContext;
        $this->currencyHelper = $currencyHelper;
    }

    public function format(int $price)
    {
        return number_format($price / 100, 2);
    }

    public function formatWithSymbol(int $price)
    {
        $currencySymbol = $this->currencyHelper->convertCurrencyCodeToSymbol(
            $this->currencyContext->getCurrencyCode()
        );

        return $this->format($price).' '.$currencySymbol;
    }
}
