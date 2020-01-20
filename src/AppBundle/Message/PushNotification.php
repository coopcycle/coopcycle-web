<?php

namespace AppBundle\Message;

class PushNotification
{
    private $content;
    private $users = [];
    private $data = [];

    public function __construct(string $content, array $users, array $data = [])
    {
        $this->content = $content;
        $this->users = $users;
        $this->data = $data;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getUsers()
    {
        return $this->users;
    }

    public function getData()
    {
        return $this->data;
    }
}
