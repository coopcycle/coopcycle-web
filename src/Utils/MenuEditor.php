<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class MenuEditor
{
    private $restaurant;
    private $menu;

    public function __construct(LocalBusiness $restaurant, $menu)
    {
        $this->restaurant = $restaurant;
        $this->menu = $menu;
    }

    public function getName()
    {
        return $this->menu->getName();
    }

     public function setName($name)
    {
        return $this->menu->setName($name);
    }

    public function getChildren()
    {
        return $this->menu->getChildren();
    }

    public function getProducts()
    {
        $products = new ArrayCollection();
        foreach ($this->restaurant->getProducts() as $product) {
            $products->add($product);
        }
        foreach ($this->menu->getChildren() as $child) {
            foreach ($child->getProducts() as $product) {
                $products->removeElement($product);
            }
        }

        return $products;
    }

    public function setProducts(Collection $products)
    {
        $this->products = $products;
    }
}
