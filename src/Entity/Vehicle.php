<?php

namespace AppBundle\Entity;

/**
 * a Vehicle represents a method of transportation with certain capacities for accomplishing tasks
 **/

class Vehicle
{
    private $id;

    private $start;

    private $end;

    public function __construct(int $id, Address $start, Address $end)
    {
        $this->id = $id;
        $this->start = $start;
        $this->end = $end;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setStart(Address $start)
    {
        $this->start = $start;
    }

    public function getStart()
    {
        return $this->start;
    }

    public function setEnd(Address $end)
    {
        $this->end = $end;
    }

    public function getEnd()
    {
        return $this->end;
    }
}
