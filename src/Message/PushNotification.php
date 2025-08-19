<?php

namespace AppBundle\Message;

use Symfony\Component\Security\Core\User\UserInterface;

class PushNotification
{
    private string $title;
    private string $body;
    private array $users = [];
    private array $data = [];

    /**
     * @param string $title The title of the push notification.
     * @param string $body The body text of the push notification.
     * @param UserInterface[] $users Array of users implementing UserInterface.
     * @param array $data Additional data to be sent with the notification.
     */
    public function __construct(string $title, string $body, array $users, array $data = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->users = $users;
        $this->data = $data;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return UserInterface[] Array of users implementing UserInterface.
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
