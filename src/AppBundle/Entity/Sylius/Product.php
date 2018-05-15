<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Product\ProductInterface;
use Sylius\Component\Product\Model\Product as BaseProduct;

class Product extends BaseProduct implements ProductInterface
{
    protected $restaurant;

    /**
     * {@inheritdoc}
     */
    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    /**
     * {@inheritdoc}
     */
    public function setRestaurant(?Restaurant $restaurant): void
    {
        $restaurant->addProduct($this);

        $this->restaurant = $restaurant;
    }
}
