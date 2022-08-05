<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;

class Package
{
    use Timestampable;

    protected $id;
    protected $name;
    protected $volumeUnits;
    protected $packageSet;
    protected $slug;

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

    /**
     * @return mixed
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param mixed $slug
     *
     * @return self
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }
}
