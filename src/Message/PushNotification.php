<?php

namespace AppBundle\Message;

use Nucleos\UserBundle\Model\UserInterface;

class PushNotification
{
    private string $title;
    private string $body;
    private array $users = [];
    private array $data = [];

    /**
     * @param string $title The title of the push notification.
     * @param string $body The body text of the push notification.
     * @param UserInterface[]|string[] $users Array of usernames or objects implementing UserInterface.
     * @param array $data Additional data to be sent with the notification.
     */
    public function __construct(string $title, string $body, array $users, array $data = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;

        $usernames = [];

        // This class needs to be serializable,
        // Convert $users to an array of strings in any case
        foreach ($users as $user) {
            if (is_object($user) && is_callable([$user, 'getUsername'])) {
                $usernames[] = $user->getUsername();
            } elseif (is_string($user)) {
                $usernames[] = $user;
            }
        }

        $this->users = $usernames;

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
     * @return string[] Array of usernames.
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
