<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;

class Vehicle
{
    use Timestampable;

    protected $id;
    protected $name;
    protected $volumeUnits;
    protected $maxWeight;

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
}
