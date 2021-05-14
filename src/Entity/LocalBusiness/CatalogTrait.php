<?php

namespace AppBundle\Entity\LocalBusiness;

use ApiPlatform\Core\Annotation\ApiSubresource;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\Serializer\Annotation\Groups;

trait CatalogTrait
{
    /**
     * @ApiSubresource
     */
    protected $products;

    /**
     * @ApiSubresource
     */
    protected $productOptions;

    protected $taxons;

    /**
     * @Groups({"restaurant"})
     */
    protected $activeMenuTaxon;

    /* Products */

    public function getProducts()
    {
        return $this->products;
    }

    public function hasProduct(ProductInterface $product)
    {
        return $this->products->contains($product);
    }

    public function addProduct(ProductInterface $product)
    {
        if (!$this->hasProduct($product)) {
            $product->setRestaurant($this);
            $this->products->add($product);
        }
    }

    public function removeProduct(ProductInterface $product)
    {
        if ($this->hasProduct($product)) {
            $this->products->removeElement($product);
            $product->setRestaurant(null);
        }
    }

    /* Options */

    public function getProductOptions()
    {
        return $this->productOptions;
    }

    public function addProductOption(ProductOptionInterface $productOption)
    {
        if (!$this->productOptions->contains($productOption)) {
            $productOption->setRestaurant($this);
            $this->productOptions->add($productOption);
        }
    }

    public function removeProductOption(ProductOptionInterface $productOption)
    {
        if ($this->productOptions->contains($productOption)) {
            $this->productOptions->removeElement($productOption);
            $productOption->setRestaurant(null);
        }
    }

    /* Menus / Taxons */

    public function getActiveMenuTaxon()
    {
        return $this->activeMenuTaxon;
    }

    public function getMenuTaxon()
    {
        return $this->activeMenuTaxon;
    }

    public function setMenuTaxon(TaxonInterface $taxon)
    {
        $this->activeMenuTaxon = $taxon;
    }

    public function hasMenu()
    {
        return null !== $this->activeMenuTaxon;
    }

    public function getTaxons()
    {
        return $this->taxons;
    }

    public function addTaxon(TaxonInterface $taxon)
    {
        // TODO Check if this is a root taxon
        $this->taxons->add($taxon);
    }

    public function removeTaxon(TaxonInterface $taxon)
    {
        if ($this->getTaxons()->contains($taxon)) {
            $this->getTaxons()->removeElement($taxon);
        }
    }
}
