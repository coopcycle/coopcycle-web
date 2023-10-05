<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Sylius\Product;
use Symfony\Component\Validator\Constraints as Assert;

class ReusablePackagings
{
    private $id;
    protected $reusablePackaging;
    protected $product;

    /**
     * @Assert\Expression(
     *   "!this.getProduct().isReusablePackagingEnabled() or (this.getProduct().isReusablePackagingEnabled() and this.getUnits() > 0)",
     *   message="product.reusablePackagingUnit.mustBeGreaterThanZero"
     * )
     */
    protected $units = 0;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getReusablePackaging()
    {
        return $this->reusablePackaging;
    }

    /**
     * @param ReusablePackaging|null $reusablePackaging
     *
     * @return self
     */
    public function setReusablePackaging(?ReusablePackaging $reusablePackaging)
    {
        $this->reusablePackaging = $reusablePackaging;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param Product|null $product
     *
     * @return self
     */
    public function setProduct(?Product $product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return float
     */
    public function getUnits(): float
    {
        return $this->units;
    }

    /**
     * @param float $units
     */
    public function setUnits(float $units)
    {
        $this->units = $units;
    }
}
