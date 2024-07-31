<?php

namespace AppBundle\Entity\Vehicle;

use ApiPlatform\Core\Annotation\ApiResource;


/**
 * @ApiResource(
 *   shortName="VehicleTrailer",
 * )
 */
class Trailer
{
    protected $id;

    protected $trailer;

    protected $vehicle;

    /**
     * Get the value of trailer
     */
    public function getTrailer()
    {
        return $this->trailer;
    }

    /**
     * Set the value of trailer
     *
     * @return  self
     */
    public function setTrailer($trailer)
    {
        $this->trailer = $trailer;

        return $this;
    }

    /**
     * Get the value of vehicle
     */
    public function getVehicle()
    {
        return $this->vehicle;
    }

    /**
     * Set the value of vehicle
     *
     * @return  self
     */
    public function setVehicle($vehicle)
    {
        $this->vehicle = $vehicle;

        return $this;
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
}
