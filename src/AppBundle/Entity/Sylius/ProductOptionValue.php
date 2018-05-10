<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductOptionValue as BaseProductOptionValue;

class ProductOptionValue extends BaseProductOptionValue implements ProductOptionValueInterface
{
    /**
     * @var int
     */
    protected $price;

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
}
