<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
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

    /**
     * {@inheritdoc}
     */
    public function getOptionValues(): Collection
    {
        $values = array_map(
            function (ProductVariantOptionValue $variantOptionValue) {
                return $variantOptionValue->getOptionValue();
            },
            $this->optionValues->toArray()
        );

        return new ArrayCollection($values);
    }

    /**
     * {@inheritdoc}
     */
    public function addOptionValue(ProductOptionValueInterface $optionValue): void
    {
        if (!$this->hasOptionValue($optionValue)) {

            $variantOptionValue = new ProductVariantOptionValue();
            $variantOptionValue->setVariant($this);
            $variantOptionValue->setOptionValue($optionValue);
            /* $variantOptionValue->setQuantity(1); */

            $this->optionValues->add($variantOptionValue);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeOptionValue(ProductOptionValueInterface $optionValue): void
    {
        if ($this->hasOptionValue($optionValue)) {
            foreach ($this->optionValues as $variantOptionValue) {
                if ($variantOptionValue->getOptionValue() === $optionValue) {
                    $this->optionValues->removeElement($variantOptionValue);
                    break;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasOptionValue(ProductOptionValueInterface $optionValue): bool
    {
        return $this->getOptionValues()->contains($optionValue);
    }

    public function addOptionValueWithQuantity(ProductOptionValueInterface $optionValue, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        if ($this->hasOptionValue($optionValue)) {
            foreach ($this->optionValues as $variantOptionValue) {
                if ($variantOptionValue->getOptionValue() === $optionValue) {
                    $variantOptionValue->setQuantity($quantity);
                }
            }
        } else {
            $variantOptionValue = new ProductVariantOptionValue();
            $variantOptionValue->setVariant($this);
            $variantOptionValue->setOptionValue($optionValue);
            $variantOptionValue->setQuantity($quantity);

            $this->optionValues->add($variantOptionValue);
        }
    }

    public function getQuantityForOptionValue(ProductOptionValueInterface $optionValue): int
    {
        foreach ($this->optionValues as $variantOptionValue) {
            if ($variantOptionValue->getOptionValue() === $optionValue) {
                return $variantOptionValue->getQuantity();
            }
        }

        return 0;
    }

    public function hasOptionValueWithQuantity(ProductOptionValueInterface $optionValue, int $quantity = 1): bool
    {
        if ($this->hasOptionValue($optionValue)) {
            foreach ($this->optionValues as $variantOptionValue) {
                if ($variantOptionValue->getOptionValue() === $optionValue && $variantOptionValue->getQuantity() === $quantity) {
                    return true;
                }
            }
        }

        return false;
    }
}
