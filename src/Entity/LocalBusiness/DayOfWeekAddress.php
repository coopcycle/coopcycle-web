<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\Address;
use AppBundle\Entity\LocalBusiness;

class DayOfWeekAddress
{
    private int $id;
    private LocalBusiness $restaurant;
    private Address $address;
    private string $daysOfWeek;

    public function getDaysOfWeek(): string
    {
        return $this->daysOfWeek;
    }

    public function setDaysOfWeek(string $daysOfWeek): void
    {
        $this->daysOfWeek = $daysOfWeek;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
    }

    public function setRestaurant(LocalBusiness $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function getRestaurant(): LocalBusiness
    {
        return $this->restaurant;
    }
}
