<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;

class BusinessRestaurantGroup extends LocalBusinessGroup
{
    use SoftDeleteable;

    private $cutoffTime;
    private $businessAccount;
    private $restaurantsWithMenu;

    public function __construct()
    {
        $this->restaurantsWithMenu = new ArrayCollection();

        parent::__construct();
    }

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
        $restaurants = $this->getRestaurants();
        if (count($restaurants) > 0) {
            return $restaurants->first()->getAddress();
        }
        return null;
    }

    public function getRestaurants()
    {
        $restaurants = array_map(function ($restaurantWithMenu) {
            return $restaurantWithMenu->getRestaurant();
        }, $this->restaurantsWithMenu->toArray());

        return new ArrayCollection($restaurants);
    }

    public function getRestaurantsWithMenu()
    {
        return $this->restaurantsWithMenu;
    }

    public function setRestaurantsWithMenu($restaurantsWithMenu)
    {
        $this->restaurantsWithMenu = $restaurantsWithMenu;

        return $this;
    }

    /**
     * @param BusinessRestaurantGroupRestaurantMenu $restaurantMenu
     */
    public function addRestaurantWithMenu(BusinessRestaurantGroupRestaurantMenu $restaurantMenu)
    {
        $this->restaurantsWithMenu->add($restaurantMenu);
    }

    /**
     * @param BusinessRestaurantGroupRestaurantMenu $restaurantMenu
     */
    public function removeRestaurantWithMenu(BusinessRestaurantGroupRestaurantMenu $restaurantMenu): void
    {
        $this->restaurantsWithMenu->removeElement($restaurantMenu);
    }
}
