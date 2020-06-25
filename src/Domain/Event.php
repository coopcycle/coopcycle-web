<?php

namespace AppBundle\Domain;

use SimpleBus\Message\Name\NamedMessage;

abstract class Event implements NamedMessage
{
    public function toPayload()
    {
        return [];
    }
}
