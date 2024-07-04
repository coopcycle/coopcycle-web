<?php

namespace AppBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use AppBundle\Entity\Vehicle;

/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"trailer"}},
 *     "denormalization_context"={"groups"={"trailer_create"}}
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
class Trailer
{
    use Timestampable;

    protected $id;

    /**
    * @Groups({"trailer", "trailer_create"})
    */
    protected $name;

    /**
    * @Groups({"trailer", "trailer_create"})
    */
    protected $maxVolumeUnits;

    /**
    * @Groups({"trailer", "trailer_create"})
    */
    protected $maxWeight;

    /**
    * @Groups({"trailer", "trailer_create"})
    */
    protected $color;

    /**
    * @Groups({"trailer", "trailer_create"})
    */
    protected $isElectric;

    /**
    * @Groups({"trailer", "trailer_create"})
    */
    protected $electricRange;

    /**
    * @Groups({"trailer", "trailer_create"})
    */
    protected $compatibleVehicles;

    public function __construct() {
        $this->compatibleVehicles = new ArrayCollection();
    }

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of maxVolumeUnits
     */
    public function getMaxVolumeUnits()
    {
        return $this->maxVolumeUnits;
    }

    /**
     * Set the value of maxVolumeUnits
     *
     * @return  self
     */
    public function setMaxVolumeUnits($maxVolumeUnits)
    {
        $this->maxVolumeUnits = $maxVolumeUnits;

        return $this;
    }

    /**
     * Get the value of maxWeight
     */
    public function getMaxWeight()
    {
        return $this->maxWeight;
    }

    /**
     * Set the value of maxWeight
     *
     * @return  self
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

    public function addCompatibleVehicle(Vehicle $vehicle)
    {
        $vehicle->getCompatibleTrailers()->add($this);
        return $this->compatibleVehicles->add($vehicle);
    }

    public function removeCompatibleVehicle(Vehicle $vehicle)
    {
        $vehicle->getCompatibleTrailers()->remove($this);
        return $this->compatibleVehicles->remove($vehicle);
    }

    /**
     * Get the value of compatibleVehicles
     */
    public function getCompatibleVehicles()
    {
        return $this->compatibleVehicles;
    }

    /**
     * Set the value of compatibleVehicles
     *
     * @return  self
     */
    public function setCompatibleVehicles($compatibleVehicles)
    {
        $this->compatibleVehicles = $compatibleVehicles;

        return $this;
    }
}