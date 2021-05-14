<?php

namespace AppBundle\Utils;

use Sylius\Bundle\CurrencyBundle\Templating\Helper\CurrencyHelperInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Twig\Extra\Intl\IntlExtension;

class PriceFormatter
{
    private $currencyContext;
    private $intl;

    public function __construct(
        CurrencyContextInterface $currencyContext,
        IntlExtension $intl)
    {
        $this->currencyContext = $currencyContext;
        $this->intl = $intl;
    }

    public function format(int $price)
    {
        return number_format($price / 100, 2);
    }

    public function formatWithSymbol(int $price)
    {
        return $this->intl->formatCurrency(
            ($price / 100),
            strtoupper($this->currencyContext->getCurrencyCode())
        );
    }
}
