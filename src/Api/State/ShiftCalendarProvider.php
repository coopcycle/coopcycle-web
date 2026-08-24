<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShiftCalendar;
use AppBundle\Service\Shift\CalendarFeed;
use Symfony\Bundle\SecurityBundle\Security;

final class ShiftCalendarProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CalendarFeed $calendarFeed)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShiftCalendar
    {
        return new ShiftCalendar($this->calendarFeed->getFeedUrl($this->security->getUser()));
    }
}
