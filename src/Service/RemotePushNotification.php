<?php

namespace AppBundle\Service;

class RemotePushNotification
{
    private string $title;
    private string $body;
    private array $data = [];

    /**
     * @param string $title The title of the push notification.
     * @param string $body The body text of the push notification.
     * @param array $data Additional data to be sent with the notification.
     */
    public function __construct(string $title, string $body, array $data = [])
    {
        $this->title = $title;
        $this->body = $body;
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

    public function getData(): array
    {
        return $this->data;
    }
}
