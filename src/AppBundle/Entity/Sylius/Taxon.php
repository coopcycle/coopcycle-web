<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\Taxon as BaseTaxon;

/**
 * @ApiResource
 */
class Taxon extends BaseTaxon
{
    private $taxonProducts;

    public function __construct()
    {
        parent::__construct();

        $this->taxonProducts = new ArrayCollection();
    }

    public function getTaxonProducts()
    {
        return $this->taxonProducts;
    }

    public function setTaxonProducts(Collection $taxonProducts)
    {
        $this->taxonProducts = $taxonProducts;
    }

    public function addProduct(ProductInterface $product)
    {
        $productTaxon = new ProductTaxon();
        $productTaxon->setTaxon($this);
        $productTaxon->setProduct($product);

        $this->taxonProducts->add($productTaxon);
    }

    public function getProducts()
    {
        return $this->taxonProducts->map(function (ProductTaxon $productTaxon): ProductInterface {
            return $productTaxon->getProduct();
        });
    }
}
