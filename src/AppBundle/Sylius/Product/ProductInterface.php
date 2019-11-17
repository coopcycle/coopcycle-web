<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Restaurant;
use Sylius\Component\Product\Model\ProductInterface as BaseProductInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;

interface ProductInterface extends BaseProductInterface
{
    const STRATEGY_FREE = 'free';
    const STRATEGY_OPTION = 'option';
    const STRATEGY_OPTION_VALUE = 'option_value';

    /**
     * @return Restaurant
     */
    public function getRestaurant(): ?Restaurant;

    /**
     * @param Restaurant $restaurant
     */
    public function setRestaurant(?Restaurant $restaurant): void;

    public function hasOptionValue(ProductOptionValueInterface $optionValue): bool;
}
