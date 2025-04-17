<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Comparable;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\Taxon as BaseTaxon;
use AppBundle\Action\Restaurant\ActivateMenu;

#[ApiResource(operations: [new Get(uriTemplate: '/restaurants/menus/{id}')], shortName: 'Menu', types: ['http://schema.org/Menu'], normalizationContext: ['groups' => ['restaurant']])]
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
