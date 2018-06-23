<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Store;
use Sylius\Component\Product\Model\ProductInterface as BaseProductInterface;

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

    /**
     * @return Store
     */
    public function getStore(): ?Store;

    /**
     * @param Store $store
     */
    public function setStore(?Store $store): void;
}
