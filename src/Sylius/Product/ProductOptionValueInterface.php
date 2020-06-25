<?php

namespace AppBundle\Sylius\Product;

use Sylius\Component\Product\Model\ProductOptionValueInterface as BaseProductOptionValueInterface;

interface ProductOptionValueInterface extends BaseProductOptionValueInterface
{
    /**
     * @return int
     */
    public function getPrice(): int;

    /**
     * @param int $price
     */
    public function setPrice(int $price): void;
}
