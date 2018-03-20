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
     * @var float
     * @Assert\NotBlank
     * @Assert\Type("float")
     */
    private $minimumCartAmount;

    /**
     * @var float
     * @Assert\NotBlank
     * @Assert\Type("float")
     */
    private $flatDeliveryPrice;

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
     * @return float
     */
    public function getMinimumCartAmount()
    {
        return $this->minimumCartAmount;
    }

    /**
     * @param float $minimumCartAmount
     */
    public function setMinimumCartAmount(float $minimumCartAmount = null)
    {
        $this->minimumCartAmount = $minimumCartAmount;
    }

    /**
     * @return float
     */
    public function getFlatDeliveryPrice()
    {
        return $this->flatDeliveryPrice;
    }

    /**
     * @param float $flatDeliveryPrice
     */
    public function setFlatDeliveryPrice(float $flatDeliveryPrice = null)
    {
        $this->flatDeliveryPrice = $flatDeliveryPrice;
    }

}
