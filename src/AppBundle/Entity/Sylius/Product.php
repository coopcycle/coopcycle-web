<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Store;
use AppBundle\Sylius\Product\ProductInterface;
use Sylius\Component\Product\Model\Product as BaseProduct;

class Product extends BaseProduct implements ProductInterface
{

    protected $enabled = false;

    protected $restaurant;

    protected $store;

    public function getLocalBusiness() {
        return $this->restaurant ? $this->restaurant : $this->store;
    }

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

    /**
     * {@inheritdoc}
     */
    public function getStore(): ?Store
    {
        return $this->store;
    }

    /**
     * {@inheritdoc}
     */
    public function setStore(?Store $store): void
    {
        $store->addProduct($this);

        $this->store = $store;
    }
}
