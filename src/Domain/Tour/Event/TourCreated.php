<?php

namespace AppBundle\Domain\Tour\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Tour\Event;

class TourCreated extends Event implements DomainEvent
{
    public static function messageName(): string
    {
        return 'tour:created';
    }

}
