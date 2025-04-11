<?php

namespace AppBundle\Message;

class WoopitWebhook
{
    public function __construct(private string $object, private string $event)
    {}

    public function getObject(): string
    {
        return $this->object;
    }

    public function getEvent(): string
    {
        return $this->event;
    }
}
