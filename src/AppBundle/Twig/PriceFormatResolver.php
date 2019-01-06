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

    public function priceFormat($cents)
    {
        return $this->priceFormatter->formatWithSymbol($cents);
    }
}
