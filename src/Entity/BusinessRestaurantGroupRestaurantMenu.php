<?php

namespace AppBundle\Entity;

class BusinessRestaurantGroupRestaurantMenu
{
    private $businessRestaurantGroup;
    private $restaurant;
    private $menu;

    public function getBusinessRestaurantGroup()
    {
        return $this->businessRestaurantGroup;
    }

    public function setBusinessRestaurantGroup($businessRestaurantGroup)
    {
        $this->businessRestaurantGroup = $businessRestaurantGroup;

        return $this;
    }

    public function getRestaurant()
    {
        return $this->restaurant;
    }

    public function setRestaurant($restaurant)
    {
        $this->restaurant = $restaurant;

        return $this;
    }

    public function getMenu()
    {
        return $this->menu;
    }

    public function setMenu($menu)
    {
        $this->menu = $menu;

        return $this;
    }

}
