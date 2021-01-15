<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;

class PackageSet
{
    use Timestampable;

    protected $id;
    protected $name;
    protected $packages;

    public function __construct()
    {
        $this->packages = new ArrayCollection();
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
    public function getPackages()
    {
        return $this->packages;
    }

    /**
     * @param mixed $packages
     *
     * @return self
     */
    public function setPackages($packages)
    {
        $this->packages = $packages;

        return $this;
    }

    public function addPackage($package)
    {
        $package->setPackageSet($this);

        $this->packages->add($package);
    }

    public function removePackage($package)
    {
        $this->packages->removeElement($package);
    }
}
