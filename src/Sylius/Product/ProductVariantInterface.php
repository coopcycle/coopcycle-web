<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery\PricingRuleSet;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductVariantInterface as BaseProductVariantInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

interface ProductVariantInterface extends BaseProductVariantInterface, TaxableInterface
{
    public function getPrice(): ?int;

    public function setPrice(?int $price): void;

    public function setTaxCategory(?TaxCategoryInterface $category): void;

    public function addOptionValueWithQuantity(ProductOptionValueInterface $optionValue, int $quantity = 1): void;

    public function hasOptionValueWithQuantity(ProductOptionValueInterface $optionValue, int $quantity = 1): bool;

    public function getQuantityForOptionValue(ProductOptionValueInterface $optionValue): int;

    public function formatQuantityForOptionValue(ProductOptionValueInterface $optionValue): int;

    public function isBusiness(): bool;

    public function getPricingRuleSet(): ?PricingRuleSet;

    public function setPricingRuleSet(?PricingRuleSet $pricingRuleSet): void;

    /**
     * Used for On Demand Delivery products
     */
    public function getOptionValuesPrice(): ?int;
}
