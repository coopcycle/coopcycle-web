<?php

namespace AppBundle\Twig;

use AppBundle\Utils\PriceFormatter;
use Twig\Extension\RuntimeExtensionInterface;

class PriceFormatResolver implements RuntimeExtensionInterface
{
    private $priceFormatter;

    public function __construct(PriceFormatter $priceFormatter)
    {
        $this->priceFormatter = $priceFormatter;
    }

    public function priceFormat($cents, $withSymbol = true)
    {
        if ($withSymbol) {

            return $this->priceFormatter->formatWithSymbol($cents);
        }

        return $this->priceFormatter->format($cents);
    }
}
