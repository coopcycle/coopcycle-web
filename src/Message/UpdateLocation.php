<?php

namespace AppBundle\Message;

class UpdateLocation
{
    private $username;
    private $locations = [];

    public function __construct(string $username, array $locations = [])
    {
        $this->username = $username;
        $this->locations = $locations;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getLocations(): array
    {
        return $this->locations;
    }
}
