<?php

namespace AppBundle\Entity\Sylius;

use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

class ProductTaxon
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var ProductInterface
     */
    protected $product;

    /**
     * @var TaxonInterface
     */
    protected $taxon;

    /**
     * @var int
     */
    protected $position;

    public function getId()
    {
        return $this->id;
    }

    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    public function setProduct(?ProductInterface $product): void
    {
        $this->product = $product;
    }

    public function getTaxon(): ?TaxonInterface
    {
        return $this->taxon;
    }

    public function setTaxon(?TaxonInterface $taxon): void
    {
        $this->taxon = $taxon;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }
}
