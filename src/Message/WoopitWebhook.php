<?php

namespace AppBundle\Message;

class WoopitWebhook
{
    public function __construct(string $object, string $event)
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
