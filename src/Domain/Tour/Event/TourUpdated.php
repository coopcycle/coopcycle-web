<?php

namespace AppBundle\Domain\Tour\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Tour\Event;

class TourUpdated extends Event implements DomainEvent
{
    public static function messageName(): string
    {
        return 'tour:updated';
    }

}
