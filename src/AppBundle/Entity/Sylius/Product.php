<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Product\ProductInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Product\Model\Product as BaseProduct;

class Product extends BaseProduct implements ProductInterface
{
    protected $restaurant;

    public function __construct()
    {
        parent::__construct();

        $this->restaurant = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant->get(0);
    }

    /**
     * {@inheritdoc}
     */
    public function setRestaurant(?Restaurant $restaurant): void
    {
        $this->restaurant->clear();
        $this->restaurant->add($restaurant);
    }
}
