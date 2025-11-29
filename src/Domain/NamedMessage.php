<?php

namespace AppBundle\Domain;

interface NamedMessage
{
    /**
     * The name of this particular type of message.
     */
    public static function messageName(): string;
}
