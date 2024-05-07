<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;

/**
 * A form to order deliveries, to be embedded anywhere.
 *
 * @ApiResource(
 *   itemOperations={
 *     "delete"={
 *       "method"="DELETE",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     }
 *   }
 * )
 */
class DeliveryForm
{
    use Timestampable;

    private $id;
    private $pricingRuleSet;
    private $timeSlot;
    private $packageSet;
    private $withVehicle = false;
    private $withWeight = false;
    private $showHomepage = false;
    private $submissions;

    public function __construct()
    {
        $this->submissions = new ArrayCollection();
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
    public function getPricingRuleSet()
    {
        return $this->pricingRuleSet;
    }

    /**
     * @param mixed $pricingRuleSet
     *
     * @return self
     */
    public function setPricingRuleSet($pricingRuleSet)
    {
        $this->pricingRuleSet = $pricingRuleSet;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimeSlot()
    {
        return $this->timeSlot;
    }

    /**
     * @param mixed $timeSlot
     *
     * @return self
     */
    public function setTimeSlot($timeSlot)
    {
        $this->timeSlot = $timeSlot;

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
     * @param mixed $packageSet
     *
     * @return self
     */
    public function setPackageSet($packageSet)
    {
        $this->packageSet = $packageSet;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getWithVehicle()
    {
        return $this->withVehicle;
    }

    /**
     * @param mixed $withVehicle
     *
     * @return self
     */
    public function setWithVehicle($withVehicle)
    {
        $this->withVehicle = $withVehicle;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getWithWeight()
    {
        return $this->withWeight;
    }

    /**
     * @param mixed $withWeight
     *
     * @return self
     */
    public function setWithWeight($withWeight)
    {
        $this->withWeight = $withWeight;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getShowHomepage()
    {
        return $this->showHomepage;
    }

    /**
     * @param mixed $showHomepage
     *
     * @return self
     */
    public function setShowHomepage($showHomepage)
    {
        $this->showHomepage = $showHomepage;

        return $this;
    }

    /**
     * Get the value of submissions
     */
    public function getSubmissions()
    {
        return $this->submissions;
    }
}
