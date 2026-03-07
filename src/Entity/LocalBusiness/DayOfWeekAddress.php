<?php

namespace AppBundle\Entity\LocalBusiness;

class DayOfWeekAddress
{
    private $id;
    private $restaurant;
    private $address;
    private string $daysOfWeek;

    public function getDaysOfWeek()
    {
        return $this->daysOfWeek;
    }

    public function getAddress()
    {
        return $this->address;
    }
}
