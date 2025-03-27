<?php

namespace AppBundle\Sylius\Product;

use Sylius\Component\Product\Model\ProductOptionValueInterface as BaseProductOptionValueInterface;

interface ProductOptionValueInterface extends BaseProductOptionValueInterface
{
    public function getPrice(): int;

    public function setPrice(int $price): void;
}
