<?php


namespace AppBundle\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 *
 * Represents a contract between a restaurant and a coop
 *
 * @ORM\Entity
 * @ORM\Table(name="contract")
 */
class Contract
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     *
     * @var Restaurant
     * @ORM\OneToOne(targetEntity="Restaurant", inversedBy="Contract", cascade={"persist"})
     * @ORM\JoinColumn(name="restaurant_id", referencedColumnName="id")
     */
    private $restaurant;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    private $minimumCartAmount;

    /**
     * @var float
     * @ORM\Column(type="float")
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
    public function setMinimumCartAmount(float $minimumCartAmount)
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
    public function setFlatDeliveryPrice(float $flatDeliveryPrice)
    {
        $this->flatDeliveryPrice = $flatDeliveryPrice;
    }

}