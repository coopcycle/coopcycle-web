<?php

namespace AppBundle\Entity;

class LocalBusinessGroupVendor
{
    private $id;
    private $hub;
    private $businessRestaurantGroup;

    public function getId()
    {
        return $this->id;
    }

    public function getHub()
    {
        return $this->hub;
    }

    public function getBusinessRestaurantGroup()
    {
        return $this->businessRestaurantGroup;
    }

    public function isHub(): bool
    {
        return null !== $this->hub;
    }

    public function isBusinessRestaurantGroup(): bool
    {
        return null !== $this->businessRestaurantGroup;
    }
}
