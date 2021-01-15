<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductOptionValueInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Sylius\Component\Product\Model\ProductOptionValue as BaseProductOptionValue;
use Symfony\Component\Validator\Constraints as Assert;

class ProductOptionValue extends BaseProductOptionValue implements ProductOptionValueInterface
{
    use ToggleableTrait;

    /**
     * @var int
     * @Assert\GreaterThanOrEqual(0)
     */
    protected $price = 0;

    /**
     * {@inheritdoc}
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * {@inheritdoc}
     */
    public function setPrice(int $price): void
    {
        $this->price = $price;
    }
}
