<?php

namespace Tests\AppBundle\Service\Shift\Compliance;

use AppBundle\Service\Shift\Compliance\ConstraintTemplates;
use AppBundle\Service\Shift\Compliance\RuleEvaluator;
use PHPUnit\Framework\TestCase;

class RuleEvaluatorTest extends TestCase
{
    private RuleEvaluator $evaluator;
    private \DateTimeImmutable $monday;

    public function setUp(): void
    {
        $this->evaluator = new RuleEvaluator();
        // The checked week: Mon 2026-07-13 .. Sun 2026-07-19
        $this->monday = new \DateTimeImmutable('2026-07-13 00:00:00');
    }

    private function shift(string $start, string $end, int $breakMinutes = 0): array
    {
        return [
            'start' => new \DateTimeImmutable($start),
            'end' => new \DateTimeImmutable($end),
            'breakMinutes' => $breakMinutes,
        ];
    }

    private function rulesOf(array $violations): array
    {
        return array_values(array_unique(array_column($violations, 'rule')));
    }

    public function testCompliantScheduleHasNoViolations()
    {
        // 5 days of 7h with a 45 min break, weekends off
        $shifts = [];
        foreach ([13, 14, 15, 16, 17] as $day) {
            $shifts[] = $this->shift(sprintf('2026-07-%d 09:00', $day), sprintf('2026-07-%d 16:45', $day), 45);
        }

        $violations = $this->evaluator->evaluate($shifts, ConstraintTemplates::rules('ccn_transport_fr'), $this->monday);

        $this->assertSame([], $violations);
    }

    public function testMaxDailyHours()
    {
        $shifts = [
            $this->shift('2026-07-13 08:00', '2026-07-13 19:30', 60), // 10.5h net
        ];

        $violations = $this->evaluator->evaluate($shifts, ['maxDailyHours' => 10.0], $this->monday);

        $this->assertCount(1, $violations);
        $this->assertSame('maxDailyHours', $violations[0]['rule']);
        $this->assertSame('2026-07-13', $violations[0]['date']);
        $this->assertSame(10.5, $violations[0]['actual']);
        $this->assertSame(10.0, $violations[0]['limit']);
    }

    public function testMaxDailyHoursSumsSplitShiftsAndDeductsBreaks()
    {
        $shifts = [
            $this->shift('2026-07-13 08:00', '2026-07-13 13:00'),     // 5h
            $this->shift('2026-07-13 17:00', '2026-07-13 22:30', 30), // 5h net
        ];

        $violations = $this->evaluator->evaluate($shifts, ['maxDailyHours' => 10.0], $this->monday);

        $this->assertSame([], $violations, 'exactly 10h net is not a violation');
    }

    public function testMaxWeeklyHours()
    {
        // 6 days x 8.5h = 51h
        $shifts = [];
        foreach ([13, 14, 15, 16, 17, 18] as $day) {
            $shifts[] = $this->shift(sprintf('2026-07-%d 09:00', $day), sprintf('2026-07-%d 17:30', $day));
        }

        $violations = $this->evaluator->evaluate($shifts, ['maxWeeklyHours' => 48.0], $this->monday);

        $this->assertCount(1, $violations);
        $this->assertSame('maxWeeklyHours', $violations[0]['rule']);
        $this->assertSame(51.0, $violations[0]['actual']);
    }

    public function testMaxAvgWeeklyHoursLooksBackOverTheWindow()
    {
        // 4-week window: three previous weeks at 48h + checked week at 48h
        // -> average 48 > 44
        $shifts = [];
        foreach ([-21, -14, -7, 0] as $offset) {
            $weekMonday = $this->monday->modify(sprintf('%+d days', $offset));
            for ($d = 0; $d < 6; $d++) {
                $day = $weekMonday->modify(sprintf('+%d days', $d));
                $shifts[] = $this->shift($day->format('Y-m-d') . ' 08:00', $day->format('Y-m-d') . ' 16:00');
            }
        }

        $violations = $this->evaluator->evaluate($shifts, [
            'maxAvgWeeklyHours' => 44.0,
            'avgWeeklyHoursWindowWeeks' => 4,
        ], $this->monday);

        $this->assertSame(['maxAvgWeeklyHours'], $this->rulesOf($violations));
        $this->assertSame(48.0, $violations[0]['actual']);
        $this->assertSame(4, $violations[0]['weeks']);
    }

    public function testMaxAvgWeeklyHoursToleratesOneBusyWeek()
    {
        // A single 48h week within an otherwise empty 12-week window: avg 4h
        $shifts = [];
        for ($d = 0; $d < 6; $d++) {
            $day = $this->monday->modify(sprintf('+%d days', $d));
            $shifts[] = $this->shift($day->format('Y-m-d') . ' 08:00', $day->format('Y-m-d') . ' 16:00');
        }

        $violations = $this->evaluator->evaluate($shifts, [
            'maxAvgWeeklyHours' => 44.0,
            'avgWeeklyHoursWindowWeeks' => 12,
        ], $this->monday);

        $this->assertSame([], $violations);
    }

    public function testMinDailyRest()
    {
        $shifts = [
            $this->shift('2026-07-13 14:00', '2026-07-13 23:00'),
            $this->shift('2026-07-14 08:00', '2026-07-14 16:00'), // only 9h after previous day
        ];

        $violations = $this->evaluator->evaluate($shifts, ['minDailyRestHours' => 11.0], $this->monday);

        $this->assertCount(1, $violations);
        $this->assertSame('minDailyRestHours', $violations[0]['rule']);
        $this->assertSame('2026-07-14', $violations[0]['date']);
        $this->assertSame(9.0, $violations[0]['actual']);
    }

    public function testMinDailyRestChecksAgainstThePreviousWeekToo()
    {
        $shifts = [
            $this->shift('2026-07-12 15:00', '2026-07-12 23:30'), // Sunday before the week
            $this->shift('2026-07-13 06:00', '2026-07-13 12:00'), // Monday, 6.5h later
        ];

        $violations = $this->evaluator->evaluate($shifts, ['minDailyRestHours' => 11.0], $this->monday);

        $this->assertCount(1, $violations);
        $this->assertSame('2026-07-13', $violations[0]['date']);
    }

    public function testMinWeeklyRest()
    {
        // A shift every single day, 8:00-22:00: max gap is 10h -> no 35h rest
        $shifts = [];
        for ($d = -2; $d < 9; $d++) {
            $day = $this->monday->modify(sprintf('%+d days', $d));
            $shifts[] = $this->shift($day->format('Y-m-d') . ' 08:00', $day->format('Y-m-d') . ' 22:00');
        }

        $violations = $this->evaluator->evaluate($shifts, ['minWeeklyRestHours' => 35.0], $this->monday);

        $this->assertCount(1, $violations);
        $this->assertSame('minWeeklyRestHours', $violations[0]['rule']);
        $this->assertSame(10.0, $violations[0]['actual']);
    }

    public function testWeekendOffSatisfiesWeeklyRest()
    {
        // Mon-Fri 09:00-17:00, free from Fri 17:00 to Mon 09:00 (64h)
        $shifts = [];
        foreach ([13, 14, 15, 16, 17] as $day) {
            $shifts[] = $this->shift(sprintf('2026-07-%d 09:00', $day), sprintf('2026-07-%d 17:00', $day));
        }

        $violations = $this->evaluator->evaluate($shifts, ['minWeeklyRestHours' => 35.0], $this->monday);

        $this->assertSame([], $violations);
    }

    public function testMinBreak()
    {
        $shifts = [
            $this->shift('2026-07-13 09:00', '2026-07-13 16:00', 15), // 6.75h net, 15 min break
        ];

        $violations = $this->evaluator->evaluate($shifts, [
            'breakThresholdHours' => 6.0,
            'minBreakMinutes' => 30,
        ], $this->monday);

        $this->assertCount(1, $violations);
        $this->assertSame('minBreakMinutes', $violations[0]['rule']);
        $this->assertSame(15, $violations[0]['actual']);
        $this->assertSame(30, $violations[0]['limit']);
    }

    public function testShortDayNeedsNoBreak()
    {
        $shifts = [
            $this->shift('2026-07-13 09:00', '2026-07-13 14:00'), // 5h, no break needed
        ];

        $violations = $this->evaluator->evaluate($shifts, [
            'breakThresholdHours' => 6.0,
            'minBreakMinutes' => 30,
        ], $this->monday);

        $this->assertSame([], $violations);
    }

    public function testMaxConsecutiveDays()
    {
        // 8 consecutive days straddling the week start (Sat before -> Sat of week)
        $shifts = [];
        for ($d = -2; $d < 6; $d++) {
            $day = $this->monday->modify(sprintf('%+d days', $d));
            $shifts[] = $this->shift($day->format('Y-m-d') . ' 09:00', $day->format('Y-m-d') . ' 12:00');
        }

        $violations = $this->evaluator->evaluate($shifts, ['maxConsecutiveDays' => 6], $this->monday);

        $this->assertCount(1, $violations);
        $this->assertSame('maxConsecutiveDays', $violations[0]['rule']);
        $this->assertSame(8, $violations[0]['actual']);
        $this->assertSame('2026-07-11', $violations[0]['from']);
        $this->assertSame('2026-07-18', $violations[0]['to']);
    }

    public function testRunOutsideTheWeekIsIgnored()
    {
        // 8 consecutive days but a month before the checked week
        $shifts = [];
        for ($d = 0; $d < 8; $d++) {
            $day = $this->monday->modify(sprintf('%+d days', $d - 35));
            $shifts[] = $this->shift($day->format('Y-m-d') . ' 09:00', $day->format('Y-m-d') . ' 12:00');
        }

        $violations = $this->evaluator->evaluate($shifts, ['maxConsecutiveDays' => 6], $this->monday);

        $this->assertSame([], $violations);
    }

    public function testDisabledRuleIsSkipped()
    {
        $shifts = [
            $this->shift('2026-07-13 06:00', '2026-07-13 21:00'), // 15h day
        ];

        $violations = $this->evaluator->evaluate($shifts, ['maxDailyHours' => null], $this->monday);

        $this->assertSame([], $violations);
    }

    public function testViolationsOutsideCheckedWeekAreNotReported()
    {
        $shifts = [
            $this->shift('2026-07-20 06:00', '2026-07-20 21:00'), // 15h, but next week
        ];

        $violations = $this->evaluator->evaluate($shifts, ['maxDailyHours' => 10.0], $this->monday);

        $this->assertSame([], $violations);
    }
}
