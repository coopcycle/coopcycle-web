<?php

namespace AppBundle\Domain\Tour\Event;

use AppBundle\Domain\Event;
use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\SilentEventInterface;
use AppBundle\Entity\Tour;

class TourUpdated extends Event implements DomainEvent, SilentEventInterface
{
    public function __construct(
        // Tour $tour,
    )
    {
    }

    public static function messageName(): string
    {
        return 'tour:updated';
    }
}
