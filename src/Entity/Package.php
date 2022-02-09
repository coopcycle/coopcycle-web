<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;

class Package
{
    use Timestampable;

    protected $id;
    /**
     * @Groups({"task", "delivery"})
     */
    protected $name;
    /**
     * @Groups({"task", "delivery"})
     */
    protected $volumeUnits;
    protected $packageSet;

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
    public function getPackageSet()
    {
        return $this->packageSet;
    }

    /**
     * @param PackageSet $packageSet
     *
     * @return self
     */
    public function setPackageSet(PackageSet $packageSet)
    {
        $this->packageSet = $packageSet;

        return $this;
    }
}
