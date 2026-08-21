<?php

namespace AppBundle\Service\Shift\Compliance;

/**
 * Evaluates one user's shifts against a set of working-time rules and returns
 * the violations found for a given week. Pure (no I/O): the caller loads the
 * shifts — including enough history/future around the checked week for the
 * rest, rolling-average and consecutive-days rules — and passes them in.
 *
 * Shifts are given as wall-clock local intervals, which is also how legal
 * working time is counted.
 */
final class RuleEvaluator
{
    /**
     * @param array<int, array{start: \DateTimeImmutable, end: \DateTimeImmutable, breakMinutes: int}> $shifts
     * @param array<string, float|int|null> $rules see ConstraintTemplates
     *
     * @return array<int, array<string, mixed>> violations: {rule, limit, actual, ...context}
     */
    public function evaluate(array $shifts, array $rules, \DateTimeImmutable $weekStart): array
    {
        usort($shifts, fn ($a, $b) => $a['start'] <=> $b['start']);

        $weekStart = $weekStart->setTime(0, 0);
        $weekEnd = $weekStart->modify('+7 days');

        $violations = [];

        $daily = $this->dailyAggregates($shifts);

        if ($limit = $this->rule($rules, 'maxDailyHours')) {
            foreach ($daily as $date => $day) {
                if ($this->inWeek($date, $weekStart, $weekEnd) && $day['netHours'] > $limit) {
                    $violations[] = [
                        'rule' => 'maxDailyHours',
                        'date' => $date,
                        'actual' => round($day['netHours'], 2),
                        'limit' => $limit,
                    ];
                }
            }
        }

        if ($limit = $this->rule($rules, 'maxWeeklyHours')) {
            $total = $this->netHoursBetween($shifts, $weekStart, $weekEnd);
            if ($total > $limit) {
                $violations[] = [
                    'rule' => 'maxWeeklyHours',
                    'actual' => round($total, 2),
                    'limit' => $limit,
                ];
            }
        }

        if ($limit = $this->rule($rules, 'maxAvgWeeklyHours')) {
            $window = (int) ($this->rule($rules, 'avgWeeklyHoursWindowWeeks') ?: 12);
            $windowStart = $weekStart->modify(sprintf('-%d days', ($window - 1) * 7));
            $average = $this->netHoursBetween($shifts, $windowStart, $weekEnd) / $window;
            if ($average > $limit) {
                $violations[] = [
                    'rule' => 'maxAvgWeeklyHours',
                    'actual' => round($average, 2),
                    'limit' => $limit,
                    'weeks' => $window,
                ];
            }
        }

        if ($limit = $this->rule($rules, 'minDailyRestHours')) {
            foreach ($daily as $date => $day) {
                if (!$this->inWeek($date, $weekStart, $weekEnd)) {
                    continue;
                }
                $previous = (new \DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
                if (!isset($daily[$previous])) {
                    continue;
                }
                $rest = ($day['firstStart']->getTimestamp() - $daily[$previous]['lastEnd']->getTimestamp()) / 3600;
                if ($rest < $limit) {
                    $violations[] = [
                        'rule' => 'minDailyRestHours',
                        'date' => $date,
                        'actual' => round(max(0, $rest), 2),
                        'limit' => $limit,
                    ];
                }
            }
        }

        if ($limit = $this->rule($rules, 'minWeeklyRestHours')) {
            if (null !== ($violation = $this->checkWeeklyRest($shifts, $limit, $weekStart, $weekEnd))) {
                $violations[] = $violation;
            }
        }

        $threshold = $this->rule($rules, 'breakThresholdHours');
        $minBreak = $this->rule($rules, 'minBreakMinutes');
        if ($threshold && $minBreak) {
            foreach ($daily as $date => $day) {
                if ($this->inWeek($date, $weekStart, $weekEnd)
                && $day['netHours'] > $threshold
                && $day['breakMinutes'] < $minBreak) {
                    $violations[] = [
                        'rule' => 'minBreakMinutes',
                        'date' => $date,
                        'actual' => $day['breakMinutes'],
                        'limit' => $minBreak,
                        'workedHours' => round($day['netHours'], 2),
                        'thresholdHours' => $threshold,
                    ];
                }
            }
        }

        if ($limit = $this->rule($rules, 'maxConsecutiveDays')) {
            foreach ($this->consecutiveRuns(array_keys($daily)) as [$from, $to, $length]) {
                if ($length > $limit
                && $from <= $weekEnd->modify('-1 day')->format('Y-m-d')
                && $to >= $weekStart->format('Y-m-d')) {
                    $violations[] = [
                        'rule' => 'maxConsecutiveDays',
                        'actual' => $length,
                        'limit' => (int) $limit,
                        'from' => $from,
                        'to' => $to,
                    ];
                }
            }
        }

        return $violations;
    }

    private function rule(array $rules, string $key): float|int|null
    {
        $value = $rules[$key] ?? null;

        return is_numeric($value) && $value > 0 ? $value + 0 : null;
    }

    private function inWeek(string $date, \DateTimeImmutable $weekStart, \DateTimeImmutable $weekEnd): bool
    {
        return $date >= $weekStart->format('Y-m-d') && $date < $weekEnd->format('Y-m-d');
    }

    private static function netHours(array $shift): float
    {
        $hours = ($shift['end']->getTimestamp() - $shift['start']->getTimestamp()) / 3600
            - $shift['breakMinutes'] / 60;

        return max(0.0, $hours);
    }

    /**
     * Per calendar day (of the shift start): net worked hours, total break,
     * first start and last end.
     *
     * @return array<string, array{netHours: float, breakMinutes: int, firstStart: \DateTimeImmutable, lastEnd: \DateTimeImmutable}>
     */
    private function dailyAggregates(array $shifts): array
    {
        $daily = [];
        foreach ($shifts as $shift) {
            $date = $shift['start']->format('Y-m-d');
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'netHours' => 0.0,
                    'breakMinutes' => 0,
                    'firstStart' => $shift['start'],
                    'lastEnd' => $shift['end'],
                ];
            }
            $daily[$date]['netHours'] += self::netHours($shift);
            $daily[$date]['breakMinutes'] += $shift['breakMinutes'];
            $daily[$date]['firstStart'] = min($daily[$date]['firstStart'], $shift['start']);
            $daily[$date]['lastEnd'] = max($daily[$date]['lastEnd'], $shift['end']);
        }

        ksort($daily);

        return $daily;
    }

    private function netHoursBetween(array $shifts, \DateTimeImmutable $start, \DateTimeImmutable $end): float
    {
        $total = 0.0;
        foreach ($shifts as $shift) {
            if ($shift['start'] >= $start && $shift['start'] < $end) {
                $total += self::netHours($shift);
            }
        }

        return $total;
    }

    /**
     * The week must contain (or touch) one continuous shift-free period of at
     * least $limit hours. The rest period may extend beyond the week bounds,
     * so gaps are computed over a window padded by $limit hours on both sides.
     */
    private function checkWeeklyRest(array $shifts, float $limit, \DateTimeImmutable $weekStart, \DateTimeImmutable $weekEnd): ?array
    {
        // No shifts in the checked week: nothing to staff, rule not relevant
        $hasShiftInWeek = false;
        foreach ($shifts as $shift) {
            if ($shift['start'] >= $weekStart && $shift['start'] < $weekEnd) {
                $hasShiftInWeek = true;
                break;
            }
        }
        if (!$hasShiftInWeek) {
            return null;
        }

        $padding = (int) ceil($limit * 3600);
        $windowStart = $weekStart->modify(sprintf('-%d seconds', $padding));
        $windowEnd = $weekEnd->modify(sprintf('+%d seconds', $padding));

        // Merge shifts intersecting the window into busy intervals
        $busy = [];
        foreach ($shifts as $shift) {
            if ($shift['end'] <= $windowStart || $shift['start'] >= $windowEnd) {
                continue;
            }
            $start = max($shift['start'], $windowStart);
            $end = min($shift['end'], $windowEnd);
            if (count($busy) > 0 && $start <= $busy[count($busy) - 1][1]) {
                $busy[count($busy) - 1][1] = max($busy[count($busy) - 1][1], $end);
            } else {
                $busy[] = [$start, $end];
            }
        }

        // Free gaps between busy intervals (and against the window edges)
        $gaps = [];
        $cursor = $windowStart;
        foreach ($busy as [$start, $end]) {
            if ($start > $cursor) {
                $gaps[] = [$cursor, $start];
            }
            $cursor = max($cursor, $end);
        }
        if ($cursor < $windowEnd) {
            $gaps[] = [$cursor, $windowEnd];
        }

        $longest = 0.0;
        foreach ($gaps as [$start, $end]) {
            // Only rest periods touching the checked week count for its rest
            if ($end <= $weekStart || $start >= $weekEnd) {
                continue;
            }
            $longest = max($longest, ($end->getTimestamp() - $start->getTimestamp()) / 3600);
            if ($longest >= $limit) {
                return null;
            }
        }

        return [
            'rule' => 'minWeeklyRestHours',
            'actual' => round($longest, 2),
            'limit' => $limit,
        ];
    }

    /**
     * @param string[] $dates sorted Y-m-d dates with at least one shift
     * @return array<int, array{0: string, 1: string, 2: int}> [from, to, length]
     */
    private function consecutiveRuns(array $dates): array
    {
        $runs = [];
        $from = null;
        $previous = null;
        $length = 0;

        foreach ($dates as $date) {
            if (null !== $previous
            && (new \DateTimeImmutable($previous))->modify('+1 day')->format('Y-m-d') === $date) {
                $length++;
            } else {
                if (null !== $from) {
                    $runs[] = [$from, $previous, $length];
                }
                $from = $date;
                $length = 1;
            }
            $previous = $date;
        }

        if (null !== $from) {
            $runs[] = [$from, $previous, $length];
        }

        return $runs;
    }
}
