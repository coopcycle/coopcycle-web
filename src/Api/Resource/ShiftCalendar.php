<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use AppBundle\Api\State\ShiftCalendarProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The current user's personal iCalendar feed URL, so the profile page can
 * offer "subscribe from Google Calendar / download .ics". The feed itself
 * (see ShiftCalendarController) is authenticated by the token in the URL.
 */
#[ApiResource(
    shortName: 'ShiftCalendar',
    operations: [
        new Get(
            uriTemplate: '/me/shift_calendar',
            provider: ShiftCalendarProvider::class,
            security: 'is_granted(\'ROLE_USER\')'
        ),
    ],
    normalizationContext: ['groups' => ['shift_calendar']]
)]
final class ShiftCalendar
{
    #[Groups(['shift_calendar'])]
    public string $feedUrl;

    public function __construct(string $feedUrl)
    {
        $this->feedUrl = $feedUrl;
    }
}
