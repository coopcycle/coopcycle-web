<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


class Package implements TaggableInterface
{
    use Timestampable;
    use SoftDeleteable;
    use TaggableTrait;

    protected $id;

    /**
     * @Assert\NotBlank
     * @Groups({"store_with_packages"})
     */
    protected $name;
    protected $packageSet;
    protected $slug;

    protected $description;

    /**
     * @Assert\NotBlank
     */
    protected $maxVolumeUnits;
    protected $averageVolumeUnits;

    /**
     * @Assert\NotBlank
    */
    protected $maxWeight;
    protected $averageWeight;

    /**
     * @Assert\Length({"min"=2, "max"=2})
     */
    protected $shortCode;

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

    /**
     * Get the value of description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the value of description
     *
     * @return  self
     */
    public function setDescription($description)
    {
        $this->description = $description;

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
     * Get the value of averageVolumeUnits
     */
    public function getAverageVolumeUnits()
    {
        return $this->averageVolumeUnits;
    }

    /**
     * Set the value of averageVolumeUnits
     *
     * @return  self
     */
    public function setAverageVolumeUnits($averageVolumeUnits)
    {
        $this->averageVolumeUnits = $averageVolumeUnits;

        return $this;
    }

    /**
     * Get the value of averageWeight
     */
    public function getAverageWeight()
    {
        return $this->averageWeight;
    }

    /**
     * Set the value of averageWeight
     *
     * @return  self
     */
    public function setAverageWeight($averageWeight)
    {
        $this->averageWeight = $averageWeight;

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
     * Get the value of shortCode
     */
    public function getShortCode()
    {
        return $this->shortCode;
    }

    /**
     * Set the value of shortCode
     *
     * @return  self
     */
    public function setShortCode($shortCode)
    {
        $this->shortCode = $shortCode;

        return $this;
    }
}
