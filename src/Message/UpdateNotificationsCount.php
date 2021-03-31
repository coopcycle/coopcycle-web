<?php

namespace AppBundle\Message;

class UpdateNotificationsCount
{
    private $username;
    private $count;

    public function __construct(string $username, int $count)
    {
        $this->username = $username;
        $this->count = $count;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
