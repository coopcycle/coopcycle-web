<?php

namespace AppBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use AppBundle\Entity\Trailer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"vehicle", "warehouse"}},
 *     "denormalization_context"={"groups"={"vehicle_create"}}
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_ADMIN')",
 *      },
 *     "post"={
 *       "method"="POST",
 *       "access_control"="is_granted('ROLE_ADMIN')",
 *      },
 *   }
 * )
 */
class Vehicle
{
    use Timestampable;

    protected $id;

    /**
    * @Groups({"vehicle", "vehicle_create"})
    * @Assert\NotBlank
    * @Assert\Type("string")
    */
    protected $name;

    /**
    * @Groups({"vehicle", "vehicle_create"})
    * @Assert\NotBlank
    * @Assert\Type("integer")
    */
    protected $volumeUnits;

    /**
    * @Groups({"vehicle", "vehicle_create"})
    * @Assert\NotBlank
    * @Assert\Type("integer")
    */
    protected $maxWeight;

    /**
    * @Groups({"vehicle", "vehicle_create"})
    * @Assert\NotBlank
    * @Assert\CssColor
    */
    protected $color;

    /**
    * @Groups({"vehicle", "vehicle_create"})
    * @Assert\NotBlank
    */
    protected $isElectric;

    /**
    * @Groups({"vehicle", "vehicle_create"})
    */
    protected $electricRange;

    /**
    * @Groups({"vehicle", "vehicle_create"})
    * @Assert\NotBlank
    * @Assert\Type(Warehouse::class)]
    */
    protected $warehouse;

    protected $compatibleTrailers;

    public function __construct() {
        $this->compatibleTrailers = new ArrayCollection();
    }

    public function addCompatibleTrailer(Trailer $trailer)
    {
        $trailer->getCompatibleVehicles()->add($this);
        $this->compatibleTrailers->add($trailer);
        return $this;
    }

    public function removeCompatibleTrailer(Trailer $trailer)
    {
        $trailer->getCompatibleVehicles()->remove($this);
        $this->compatibleTrailers->remove($trailer);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getVolumeUnits()
    {
        return $this->volumeUnits;
    }

    /**
     * @param mixed $volumeUnits
     *
     * @return self
     */
    public function setVolumeUnits($volumeUnits)
    {
        $this->volumeUnits = $volumeUnits;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxWeight()
    {
        return $this->maxWeight;
    }

    /**
     * @param mixed $maxWeight
     *
     * @return self
     */
    public function setMaxWeight($maxWeight)
    {
        $this->maxWeight = $maxWeight;

        return $this;
    }

    /**
     * Get the value of color
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set the value of color
     *
     * @return  self
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get the value of isElectric
     */
    public function getIsElectric()
    {
        return $this->isElectric;
    }

    /**
     * Set the value of isElectric
     *
     * @return  self
     */
    public function setIsElectric($isElectric)
    {
        $this->isElectric = $isElectric;

        return $this;
    }

    /**
     * Get the value of electricRange
     */
    public function getElectricRange()
    {
        return $this->electricRange;
    }

    /**
     * Set the value of electricRange
     *
     * @return  self
     */
    public function setElectricRange($electricRange)
    {
        $this->electricRange = $electricRange;

        return $this;
    }

    /**
     * Get the value of warehouse
     */
    public function getWarehouse()
    {
        return $this->warehouse;
    }

    /**
     * Set the value of warehouse
     *
     * @return  self
     */
    public function setWarehouse($warehouse)
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    /**
     * Get the value of compatibleTrailers
     */
    public function getCompatibleTrailers()
    {
        return $this->compatibleTrailers;
    }

    /**
     * Set the value of compatibleTrailers
     *
     * @return  self
     */
    public function setCompatibleTrailers($compatibleTrailers)
    {
        $this->compatibleTrailers = $compatibleTrailers;

        return $this;
    }
}
