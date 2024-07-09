<?php

namespace AppBundle\Entity\Vehicle;

use Symfony\Component\Serializer\Annotation\Groups;

class Trailer
{
    protected $id;

    protected $trailer;

    /**
    * @Groups({"vehicle", "trailer_create"})
    */
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
}
