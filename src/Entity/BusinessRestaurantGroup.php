<?php

namespace AppBundle\Entity;

class BusinessRestaurantGroup extends LocalBusinessGroup
{
    private $cutoffTime;
    private $businessAccount;

    public function getCutoffTime()
    {
        return $this->cutoffTime;
    }

    public function setCutoffTime($cutoffTime)
    {
        $this->cutoffTime = $cutoffTime;

        return $this;
    }

    public function getBusinessAccount()
    {
        return $this->businessAccount;
    }

    public function setBusinessAccount($businessAccount)
    {
        $this->businessAccount = $businessAccount;

        return $this;
    }

    /**
     * @param LocalBusiness $restaurant
     */
    public function addRestaurant(LocalBusiness $restaurant)
    {
        if (!$this->restaurants->contains($restaurant)) {
            $this->restaurants->add($restaurant);
        }
    }

    /**
     * @param LocalBusiness $restaurant
     */
    public function removeRestaurant(LocalBusiness $restaurant): void
    {
        $this->restaurants->removeElement($restaurant);
    }

    /**
     * TODO: should be defined how will be handle i.e. the order pickup address
     */
    public function getAddress()
    {
        if (count($this->restaurants) > 0) {
            return $this->restaurants->first->getAddress();
        }
        return null;
    }
}
