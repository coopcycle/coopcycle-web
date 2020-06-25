<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductOptionInterface;

class ProductOptions
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
     * @var ProductOptionInterface
     */
    protected $option;

    /**
     * @var int
     */
    protected $position;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param ProductInterface $product
     *
     * @return self
     */
    public function setProduct(ProductInterface $product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return ProductOptionInterface
     */
    public function getOption()
    {
        return $this->option;
    }

    /**
     * @param ProductOptionInterface $option
     *
     * @return self
     */
    public function setOption(ProductOptionInterface $option)
    {
        $this->option = $option;

        return $this;
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param int $position
     *
     * @return self
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }
}
