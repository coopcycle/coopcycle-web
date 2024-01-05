<?php

namespace AppBundle\Entity;

use AppBundle\Entity\BusinessRestaurantGroup;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

class BusinessRestaurantGroupPriceWithTax
{
    /**
     * @var BusinessRestaurantGroup
     */
    private $businessRestaurantGroup;

    /**
     * @var int
     */
    private $price;

    /**
     * @var TaxCategoryInterface
     */
    private $taxCategory;

    private $priceWithTax;

    public function __construct(BusinessRestaurantGroup $businessRestaurantGroup = null, $price = null, $taxCategory = null)
    {
        $this->businessRestaurantGroup = $businessRestaurantGroup;
        $this->price = $price;
        $this->taxCategory = $taxCategory;
    }

    /**
     * @return mixed
     */
    public function getBusinessRestaurantGroup()
    {
        return $this->businessRestaurantGroup;
    }

    /**
     * @param mixed $businessRestaurantGroup
     *
     * @return self
     */
    public function setBusinessRestaurantGroup($businessRestaurantGroup)
    {
        $this->businessRestaurantGroup = $businessRestaurantGroup;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param mixed $price
     *
     * @return self
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTaxCategory()
    {
        return $this->taxCategory;
    }

    /**
     * @param mixed $taxCategory
     *
     * @return self
     */
    public function setTaxCategory($taxCategory)
    {
        $this->taxCategory = $taxCategory;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPriceWithTax()
    {
        return $this->priceWithTax;
    }

    /**
     * @param mixed $priceWithTax
     *
     * @return self
     */
    public function setPriceWithTax($priceWithTax)
    {
        $this->priceWithTax = $priceWithTax;

        return $this;
    }
}
