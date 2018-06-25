<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Base\LocalBusiness;
use AppBundle\Entity\Sylius\Taxon;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class MenuEditor
{
    private $localBusiness;
    private $menu;

    public function __construct(LocalBusiness $localBusiness, Taxon $menu)
    {
        $this->localBusiness = $localBusiness;
        $this->menu = $menu;
    }

    public function getChildren()
    {
        return $this->menu->getChildren();
    }

    public function getProducts()
    {
        $products = new ArrayCollection();
        foreach ($this->localBusiness->getProducts() as $product) {
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
