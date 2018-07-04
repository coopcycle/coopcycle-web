<?php

namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a contract between a restaurant and a coop
 */
class Contract
{
    private $id;

    /**
     * @var Restaurant
     */
    private $restaurant;

    /**
     * @var int
     * @Assert\NotBlank
     * @Assert\Type("integer")
     */
    private $minimumCartAmount;

    /**
     * @var int
     * The amount (in cents) charged by the platform.
     * @Assert\NotBlank
     * @Assert\Type("integer")
     */
    private $flatDeliveryPrice;

    /**
     * @var int
     * The amount (in cents) paid by the customer.
     * @Assert\NotBlank
     * @Assert\Type("integer")
     */
    private $customerAmount = 0;

    /**
     * @var float
     * @Assert\NotBlank
     * @Assert\Type("float")
     */
    private $feeRate;

    /**
     * @var bool
     * Restaurant pays Stripe fee?
     * @Assert\Type("bool")
     */
    private $restaurantPaysStripeFee = true;

    /**
     * @return Restaurant
     */
    public function getRestaurant()
    {
        return $this->restaurant;
    }

    /**
     * @param Restaurant $restaurant
     */
    public function setRestaurant(Restaurant $restaurant)
    {
        $this->restaurant = $restaurant;
    }

    /**
     * @return int
     */
    public function getMinimumCartAmount()
    {
        return $this->minimumCartAmount;
    }

    /**
     * @param int $minimumCartAmount
     */
    public function setMinimumCartAmount(int $minimumCartAmount)
    {
        $this->minimumCartAmount = $minimumCartAmount;
    }

    /**
     * @return int
     */
    public function getFlatDeliveryPrice()
    {
        return $this->flatDeliveryPrice;
    }

    /**
     * @param int $flatDeliveryPrice
     */
    public function setFlatDeliveryPrice(int $flatDeliveryPrice)
    {
        $this->flatDeliveryPrice = $flatDeliveryPrice;
    }

    /**
     * @return float
     */
    public function getFeeRate()
    {
        return $this->feeRate;
    }

    /**
     * @param float $feeRate
     * @return Contract;
     */
    public function setFeeRate(float $feeRate)
    {
        $this->feeRate = $feeRate;

        return $this;
    }

    public function getCustomerAmount()
    {
        return $this->customerAmount;
    }

    public function setCustomerAmount(int $customerAmount)
    {
        $this->customerAmount = $customerAmount;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRestaurantPaysStripeFee(): bool
    {
        return $this->restaurantPaysStripeFee;
    }

    /**
     * @param bool $restaurantPaysStripeFee
     */
    public function setRestaurantPaysStripeFee(bool $restaurantPaysStripeFee): void
    {
        $this->restaurantPaysStripeFee = $restaurantPaysStripeFee;
    }

}
