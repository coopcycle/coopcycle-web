<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Comparable;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\Taxon as BaseTaxon;
use AppBundle\Action\Restaurant\ActivateMenu;

/**
 * @ApiResource(iri="http://schema.org/Menu",
 *   shortName="Menu",
 *   attributes={
 *     "normalization_context"={"groups"={"restaurant"}}
 *   },
 *   collectionOperations={},
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "path"="/restaurants/menus/{id}",
 *     }
 *   }
 * )
 */
class Taxon extends BaseTaxon implements Comparable
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

    /**
     * {@inheritdoc}
     *
     * @see https://github.com/Sylius/Sylius/issues/10797
     * @see https://github.com/Sylius/Sylius/pull/11329
     * @see https://github.com/Atlantic18/DoctrineExtensions/pull/2185
     */
    public function compareTo($other)
    {
        return $this->code === $other->getCode();
    }
}
