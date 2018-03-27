<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductVariant as BaseProductVariant;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

class ProductVariant extends BaseProductVariant implements ProductVariantInterface
{
    /**
     * @var int
     */
    protected $price;

    /**
     * @var TaxCategoryInterface
     */
    protected $taxCategory;

    /**
     * {@inheritdoc}
     */
    public function getPrice(): ?int
    {
        return $this->price;
    }

    /**
     * {@inheritdoc}
     */
    public function setPrice(?int $price): void
    {
        $this->price = $price;
    }

    /**
     * {@inheritdoc}
     */
    public function getTaxCategory(): ?TaxCategoryInterface
    {
        return $this->taxCategory;
    }

    /**
     * {@inheritdoc}
     */
    public function setTaxCategory(?TaxCategoryInterface $category): void
    {
        $this->taxCategory = $category;
    }

}
