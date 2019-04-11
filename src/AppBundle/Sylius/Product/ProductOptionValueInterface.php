<?php

namespace AppBundle\Sylius\Product;

use Sylius\Component\Product\Model\ProductOptionValueInterface as BaseProductOptionValueInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

interface ProductOptionValueInterface extends BaseProductOptionValueInterface, TaxableInterface
{
    /**
     * @return int|null
     */
    public function getPrice(): ?int;

    /**
     * @param int|null $price
     */
    public function setPrice(?int $price): void;

    /**
     * @param TaxCategoryInterface|null $category
     */
    public function setTaxCategory(?TaxCategoryInterface $category): void;
}
