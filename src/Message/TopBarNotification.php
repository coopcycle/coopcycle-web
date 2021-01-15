<?php

namespace AppBundle\Message;

class TopBarNotification
{
    private $usernames = [];
    private $message;

    public function __construct(array $usernames, string $message)
    {
        $this->usernames = $usernames;
        $this->message = $message;
    }

    public function getUsernames(): array
    {
        return $this->usernames;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
