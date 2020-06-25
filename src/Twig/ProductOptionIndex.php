<?php

namespace AppBundle\Twig;

use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductOptionInterface;

class ProductOptionIndex
{
    private $index;

    public function __construct()
    {
        $this->index = 0;
    }

    public function incrementValue(ProductOptionValueInterface $optionValue)
    {
        if ($optionValue->getOption()->isAdditional()) {
            $this->index = $this->index + 1;
        }
    }

    public function incrementOption(ProductOptionInterface $option)
    {
        if (!$option->isAdditional()) {
            $this->index = $this->index + 1;
        }
    }

    public function __toString()
    {
        return strval($this->index);
    }
}
