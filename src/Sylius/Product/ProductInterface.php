<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\LocalBusiness;
use Sylius\Component\Product\Model\ProductInterface as BaseProductInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;

interface ProductInterface extends BaseProductInterface
{
    public function hasOptionValue(ProductOptionValueInterface $optionValue): bool;

    public function getPositionForOption(ProductOptionInterface $option): int;

    /**
     * @return LocalBusiness|null
     */
    public function getRestaurant(): ?LocalBusiness;

    public function isAlcohol(): bool;
}
