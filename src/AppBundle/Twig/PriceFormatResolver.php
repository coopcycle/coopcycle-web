<?php

namespace AppBundle\Twig;

use Sylius\Bundle\CurrencyBundle\Templating\Helper\CurrencyHelperInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Twig\Extension\RuntimeExtensionInterface;

class PriceFormatResolver implements RuntimeExtensionInterface
{
    private $currencyContext;
    private $currencyHelper;

    public function __construct(CurrencyContextInterface $currencyContext, CurrencyHelperInterface $currencyHelper)
    {
        $this->currencyContext = $currencyContext;
        $this->currencyHelper = $currencyHelper;
    }

    public function priceFormat($cents)
    {
        $currencyCode = $this->currencyContext->getCurrencyCode();
        return number_format($cents / 100, 2) . ' ' . $this->currencyHelper->convertCurrencyCodeToSymbol($currencyCode);
    }
}
