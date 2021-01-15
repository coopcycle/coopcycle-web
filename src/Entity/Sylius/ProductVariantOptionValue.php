<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductVariant as BaseProductVariant;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

class ProductVariantOptionValue
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var ProductVariantInterface
     */
    protected $variant;

    /**
     * @var ProductOptionValueInterface
     */
    protected $optionValue;

    /**
     * @var int
     */
    protected $quantity = 1;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ProductVariantInterface
     */
    public function getVariant(): ?ProductVariantInterface
    {
        return $this->variant;
    }

    /**
     * @param ProductVariantInterface $variant
     *
     * @return self
     */
    public function setVariant(ProductVariantInterface $variant)
    {
        $this->variant = $variant;

        return $this;
    }

    /**
     * @return ProductOptionValueInterface
     */
    public function getOptionValue(): ?ProductOptionValueInterface
    {
        return $this->optionValue;
    }

    /**
     * @param ProductOptionValueInterface $optionValue
     *
     * @return self
     */
    public function setOptionValue(ProductOptionValueInterface $optionValue)
    {
        $this->optionValue = $optionValue;

        return $this;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     *
     * @return self
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }
}
