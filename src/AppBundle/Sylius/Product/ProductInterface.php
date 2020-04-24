<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\LocalBusiness;
use Sylius\Component\Product\Model\ProductInterface as BaseProductInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;

interface ProductInterface extends BaseProductInterface
{
    /**
     * @return LocalBusiness
     */
    public function getRestaurant(): ?LocalBusiness;

    /**
     * @param LocalBusiness $restaurant
     */
    public function setRestaurant(?LocalBusiness $restaurant): void;

    public function hasOptionValue(ProductOptionValueInterface $optionValue): bool;
}
