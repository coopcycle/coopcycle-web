<?php

namespace AppBundle\Message;

class Location
{
    private $username;
    private $coordinates;

    public function __construct($username, array $coordinates)
    {
        $this->username = $username;
        $this->coordinates = $coordinates;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getCoordinates()
    {
        return $this->coordinates;
    }
}
