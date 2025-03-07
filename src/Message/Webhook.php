<?php

namespace AppBundle\Message;

class Webhook
{
    public function __construct(private string $object, private string $event)
    {
        $this->object = $object;
        $this->event = $event;
    }

    public function getObject(): string
    {
        return $this->object;
    }

    public function getEvent(): string
    {
        return $this->event;
    }
}
