<?php

namespace AppBundle\Vroom;

use Geocoder\Model\Coordinates;

/**
 * a Vehicle represents a method of transportation with certain capacities for accomplishing tasks.
 * vehicles will have an address that they start from and end at. for instance, if a vehicle must start from homebase 1
 * and needs to go to point A, point B, point C, and point D in some arbitrary order, and then it must return to
 * homebase 2, start would be homebase 1 and end would be homebase 2.
 */

class Vehicle
{

    private $id; // an int representing a unique id for a vehicle

    private $profile;

    private $start; // an address that this vehicle starts its route from

    private $end; // an address that this vehicle needs to end at

    public function __construct(int $id, string $profile, Coordinates $start = null, Coordinates $end = null)
    {
        $this->id = $id;
        $this->profile = $profile;
        $this->start = $start;
        $this->end = $end;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getProfile()
    {
        return $this->profile;
    }

    public function setStart(Coordinates $start)
    {
        $this->start = $start;
    }

    public function getStart()
    {
        return $this->start;
    }

    public function setEnd(Coordinates $end)
    {
        $this->end = $end;
    }

    public function getEnd()
    {
        return $this->end;
    }
}
