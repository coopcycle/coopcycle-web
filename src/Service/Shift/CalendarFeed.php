<?php

namespace AppBundle\Service\Shift;

use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Personal iCalendar (RFC 5545) feed of a user's shift assignments, so
 * couriers can subscribe from Google Calendar / Apple Calendar / Outlook and
 * have their schedule stay in sync.
 *
 * Calendar apps fetch the feed URL server-side, without cookies or JWT, so the
 * URL itself carries authentication: a per-user HMAC token derived from the
 * app secret. The URL is only ever shown to the logged-in user.
 */
final class CalendarFeed
{
    // How far back/ahead the feed reaches. Calendar apps keep their own copy
    // of past events, this only bounds what we serve on each refresh.
    private const PAST = '-8 weeks';
    private const FUTURE = '+1 year';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.secret%')] private readonly string $secret)
    {
    }

    public function getToken(User $user): string
    {
        return hash_hmac('sha256', sprintf('shift-calendar.%d', $user->getId()), $this->secret);
    }

    public function isTokenValid(User $user, string $token): bool
    {
        return hash_equals($this->getToken($user), $token);
    }

    public function getFeedUrl(User $user): string
    {
        return $this->urlGenerator->generate('shift_calendar_feed', [
            'id' => $user->getId(),
            'token' => $this->getToken($user),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function render(User $user): string
    {
        $timezone = new \DateTimeZone($this->settingsManager->get('timezone') ?: date_default_timezone_get());
        $utc = new \DateTimeZone('UTC');

        $shifts = $this->entityManager->getRepository(Shift::class)->findForUserBetween(
            $user,
            new \DateTime(self::PAST),
            new \DateTime(self::FUTURE)
        );

        $brandName = $this->settingsManager->get('brand_name') ?: 'CoopCycle';
        $host = $this->urlGenerator->getContext()->getHost();
        $now = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CoopCycle//Shift Planning//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            self::fold(sprintf('X-WR-CALNAME:%s', self::escape(
                sprintf('%s — %s', $brandName, $this->translator->trans('profile.myShifts'))
            ))),
            // Hint for clients that honor it; Google uses its own cadence
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];

        foreach ($shifts as $shift) {
            // Shift types are instance-configurable; fall back to the raw
            // type name when there is no translation for it
            $typeKey = sprintf('shifts.type.%s', $shift->getType());
            $summary = $this->translator->trans($typeKey);
            if ($summary === $typeKey) {
                $summary = ucfirst($shift->getType());
            }

            $description = [];
            if ($shift->getBreakMinutes() > 0) {
                $description[] = $this->translator->trans('shifts.calendar.break', [
                    '%minutes%' => $shift->getBreakMinutes(),
                ]);
            }
            if (!empty($shift->getComment())) {
                $description[] = $shift->getComment();
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = sprintf('UID:shift-%d@%s', $shift->getId(), $host);
            $lines[] = sprintf('DTSTAMP:%s', $now);
            $lines[] = sprintf('DTSTART:%s', self::toUtc($shift->getStartsAt(), $timezone, $utc));
            $lines[] = sprintf('DTEND:%s', self::toUtc($shift->getEndsAt(), $timezone, $utc));
            $lines[] = self::fold(sprintf('SUMMARY:%s', self::escape(sprintf('%s — %s', $summary, $brandName))));
            if (count($description) > 0) {
                $lines[] = self::fold(sprintf('DESCRIPTION:%s', self::escape(implode("\n", $description))));
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Shifts are stored as wall-clock instance-local time; calendars get
     * unambiguous UTC instants (avoids shipping a VTIMEZONE block).
     */
    private static function toUtc(\DateTime $localWallClock, \DateTimeZone $timezone, \DateTimeZone $utc): string
    {
        return (new \DateTimeImmutable($localWallClock->format('Y-m-d H:i:s'), $timezone))
            ->setTimezone($utc)
            ->format('Ymd\THis\Z');
    }

    /**
     * RFC 5545 text escaping: backslash, semicolon, comma, newline.
     */
    private static function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n'],
            $text
        );
    }

    /**
     * RFC 5545 line folding: content lines longer than 75 octets continue on
     * the next line after a single space.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $offset = 0;
        $max = 74; // leave room for the leading space on continuations

        while ($offset < strlen($line)) {
            // mb_strcut never splits a UTF-8 sequence
            $chunk = mb_strcut($line, $offset, $max);
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        return implode("\r\n ", $chunks);
    }
}
