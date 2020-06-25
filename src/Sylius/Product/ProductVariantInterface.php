<?php

namespace AppBundle\Sylius\Product;

use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductVariantInterface as BaseProductVariantInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

interface ProductVariantInterface extends BaseProductVariantInterface, TaxableInterface
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

    public function addOptionValueWithQuantity(ProductOptionValueInterface $optionValue, int $quantity = 1): void;

    public function hasOptionValueWithQuantity(ProductOptionValueInterface $optionValue, int $quantity = 1): bool;

    public function getQuantityForOptionValue(ProductOptionValueInterface $optionValue): int;
}
