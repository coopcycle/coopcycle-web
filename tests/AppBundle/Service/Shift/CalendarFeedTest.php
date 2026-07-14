<?php

namespace Tests\AppBundle\Service\Shift;

use AppBundle\Entity\Shift;
use AppBundle\Entity\ShiftRepository;
use AppBundle\Entity\User;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\Shift\CalendarFeed;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarFeedTest extends TestCase
{
    private CalendarFeed $calendarFeed;
    private $shiftRepository;

    public function setUp(): void
    {
        $this->shiftRepository = $this->createMock(ShiftRepository::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->with(Shift::class)
            ->willReturn($this->shiftRepository);

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager
            ->method('get')
            ->willReturnMap([
                ['timezone', 'Europe/Paris'],
                ['brand_name', 'Acme Coop'],
            ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(fn ($id, $params = []) => match ($id) {
                'shifts.type.drive' => 'Delivery shift',
                'shifts.calendar.break' => sprintf('Break: %s min', $params['%minutes%'] ?? ''),
                'profile.myShifts' => 'My shifts',
                default => $id,
            });

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('getContext')
            ->willReturn(new RequestContext('', 'GET', 'demo.coopcycle.org'));
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(fn ($route, $params) => sprintf(
                'https://demo.coopcycle.org/calendar/shifts/%d/%s/shifts.ics', $params['id'], $params['token']
            ));

        $this->calendarFeed = new CalendarFeed(
            $entityManager,
            $settingsManager,
            $translator,
            $urlGenerator,
            's3cr3t'
        );
    }

    private function user(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    private function shift(int $id, string $startsAt, string $endsAt, int $breakMinutes = 0, ?string $comment = null): Shift
    {
        $shift = new Shift();
        $shift->setType('drive');
        $shift->setStartsAt(new \DateTime($startsAt));
        $shift->setEndsAt(new \DateTime($endsAt));
        $shift->setBreakMinutes($breakMinutes);
        $shift->setComment($comment);

        $reflection = new \ReflectionProperty(Shift::class, 'id');
        $reflection->setValue($shift, $id);

        return $shift;
    }

    public function testTokenIsDeterministicAndVerifiable()
    {
        $user = $this->user(7);

        $token = $this->calendarFeed->getToken($user);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertSame($token, $this->calendarFeed->getToken($user));
        $this->assertTrue($this->calendarFeed->isTokenValid($user, $token));
        $this->assertFalse($this->calendarFeed->isTokenValid($user, str_repeat('0', 64)));
        $this->assertNotSame($token, $this->calendarFeed->getToken($this->user(8)));
    }

    public function testFeedUrlContainsIdAndToken()
    {
        $user = $this->user(7);

        $url = $this->calendarFeed->getFeedUrl($user);

        $this->assertStringContainsString('/calendar/shifts/7/', $url);
        $this->assertStringContainsString($this->calendarFeed->getToken($user), $url);
    }

    public function testRendersWallClockTimesAsUtc()
    {
        // 12:00 wall-clock in Paris during DST is 10:00 UTC
        $this->shiftRepository
            ->method('findForUserBetween')
            ->willReturn([$this->shift(1, '2026-07-13 12:00:00', '2026-07-13 15:00:00')]);

        $ics = $this->calendarFeed->render($this->user(7));

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("DTSTART:20260713T100000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20260713T130000Z\r\n", $ics);
        $this->assertStringContainsString('UID:shift-1@demo.coopcycle.org', $ics);
        $this->assertStringContainsString('SUMMARY:Delivery shift — Acme Coop', $ics);
        $this->assertStringContainsString('X-WR-CALNAME:Acme Coop — My shifts', $ics);
    }

    public function testEscapesTextAndIncludesBreakInDescription()
    {
        $this->shiftRepository
            ->method('findForUserBetween')
            ->willReturn([$this->shift(2, '2026-07-13 09:00:00', '2026-07-13 17:00:00', 30, "Ring the bell; twice, please\nThen wait")]);

        $ics = $this->calendarFeed->render($this->user(7));

        $this->assertStringContainsString('DESCRIPTION:Break: 30 min\n', $ics);
        $this->assertStringContainsString('Ring the bell\; twice\, please\nThen wait', $ics);
    }

    public function testFoldsLongLines()
    {
        $this->shiftRepository
            ->method('findForUserBetween')
            ->willReturn([$this->shift(3, '2026-07-13 09:00:00', '2026-07-13 17:00:00', 0, str_repeat('a', 200))]);

        $ics = $this->calendarFeed->render($this->user(7));

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line), sprintf('Line too long: %s', $line));
        }

        // Unfolding restores the original content
        $this->assertStringContainsString(str_repeat('a', 200), str_replace("\r\n ", '', $ics));
    }

    public function testEmptyScheduleStillRendersAValidCalendar()
    {
        $this->shiftRepository
            ->method('findForUserBetween')
            ->willReturn([]);

        $ics = $this->calendarFeed->render($this->user(7));

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }
}
