<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\DataType\NumRange;
use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Validator\Constraints\ProductOption as AssertProductOption;
use Sylius\Component\Product\Model\ProductOption as BaseProductOption;

/**
 * @AssertProductOption
 */
class ProductOption extends BaseProductOption implements ProductOptionInterface
{
    /**
     * @var string
     */
    protected $strategy = ProductOptionInterface::STRATEGY_FREE;

    /**
     * @var int
     */
    protected $price;

    /**
     * @var boolean
     */
    protected $additional = false;

    protected $valuesRange;

    protected $deletedAt;

    /**
     * {@inheritdoc}
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }

    /**
     * {@inheritdoc}
     */
    public function setStrategy(string $strategy): void
    {
        $this->strategy = $strategy;
    }

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
    public function setAdditional(bool $additional): void
    {
        $this->additional = $additional;
    }

    /**
     * {@inheritdoc}
     */
    public function isAdditional(): bool
    {
        return $this->additional;
    }

    public function setRestaurant(Restaurant $restaurant)
    {
        $restaurant->addProductOption($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesRange(): ?NumRange
    {
        return $this->valuesRange;
    }

    public function setValuesRange($range)
    {
        $this->valuesRange = $range;

        return $this;
    }
}
