<?php

namespace AppBundle\Domain;


abstract class Event implements NamedMessage
{
    public function toPayload()
    {
        return [];
    }
}
