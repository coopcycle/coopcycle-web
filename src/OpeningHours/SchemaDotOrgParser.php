<?php

namespace AppBundle\OpeningHours;

use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;
use Spatie\OpeningHours\TimeRange;

class SchemaDotOrgParser
{
    private static $daysOfWeek = [
        'Mo' => 'monday',
        'Tu' => 'tuesday',
        'We' => 'wednesday',
        'Th' => 'thursday',
        'Fr' => 'friday',
        'Sa' => 'saturday',
        'Su' => 'sunday'
    ];

    public static function parseCollection(array $openingHoursCollection)
    {
        $config = array_combine(
            array_values(self::$daysOfWeek),
            array_pad([], count(self::$daysOfWeek), [])
        );

        foreach ($openingHoursCollection as $text) {

            preg_match('/([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})/', $text, $matches);

            $timeRange = $matches[0];

            preg_match('/(Mo|Tu|We|Th|Fr|Sa|Su)+(?:[,-](Mo|Tu|We|Th|Fr|Sa|Su)*)*/', $text, $matches);

            $days = $matches[0];

            $daysOfWeek = [];

            // It is a single day
            if (false === strpos($days, '-') && false === strpos($days, ',')) {
                $config[self::$daysOfWeek[$days]][] = $timeRange;
            } else {
                foreach (explode(',', $days) as $part) {
                    if (false !== strpos($part, '-')) {
                        [ $start, $end ] = explode('-', $part);
                        $append = false;
                        foreach (self::$daysOfWeek as $short => $long) {
                            if ($start === $short) {
                                $append = true;
                            }
                            if ($append) {
                                $config[$long][] = $timeRange;
                            }
                            if ($end === $short) {
                                $append = false;
                            }
                        }
                    } else {
                        $config[self::$daysOfWeek[$part]][] = $timeRange;
                    }
                }
            }
        }

        return $config;
    }

    public static function parseExceptions(Collection $closingRules, array $ranges)
    {
        // @see https://github.com/spatie/opening-hours/issues/85

        $dynamicClosedRanges = [];
        foreach ($closingRules as $closingRule) {

            $startDate = Carbon::instance($closingRule->getStartDate());
            $endDate = Carbon::instance($closingRule->getEndDate());

            $diff = $endDate->diffInDays($startDate);

            if ($startDate->isSameDay($endDate)) {
                $dynamicClosedRanges[$startDate->format('Y-m-d')] = [ $startDate->format('H:i').'-'.$endDate->format('H:i') ];
            } else {
                $cursor = clone $startDate;
                do {

                    $start = $cursor->format('H:i');
                    if ($cursor->isSameDay($endDate)) {
                        $end = $endDate->format('H:i');
                    } else {
                        $cursor->setTime(23, 59);
                        $end = $cursor->format('H:i');
                    }

                    $dynamicClosedRanges[$cursor->format('Y-m-d')] = [ $start.'-'.$end ];

                    $cursor->add(1, 'day');
                    $cursor->setTime(0, 0, 0);

                }  while ($cursor < $endDate);
            }
        }

        $exceptions = [];

        foreach ($dynamicClosedRanges as $day => $closedRanges) {

            $weekDay = strtolower((new \DateTime($day))->format('l'));
            $dayRanges = \Spatie\OpeningHours\OpeningHoursForDay::fromStrings($ranges[$weekDay]);
            $newRanges = [];

            if ($closedRanges === [ '00:00-23:59' ]) {
                $exceptions[$day] = [];
                continue;
            }

            foreach ($dayRanges as $dayRange) {

                /* @var TimeRange $dayRange */
                foreach ($closedRanges as $exceptionRange) {
                    $range = TimeRange::fromString($exceptionRange);

                    if ($range->overlaps($dayRange)) {
                        if ($dayRange->start()->isSameOrAfter($range->start()) && $range->end()->isSameOrAfter($dayRange->end())) {
                            continue 2;
                        }

                        // $range ->    |-------|***| <- $newRanges[]
                        // $dayRange ->     |-------|

                        if ($range->end()->isBefore($dayRange->end())) {
                            $newRanges[] = TimeRange::fromString($range->end()->format() . '-' . $dayRange->end()->format())->format();
                        } else {
                            $newRanges[] = TimeRange::fromString($dayRange->start()->format() . '-' . $range->start()->format())->format();
                        }

                        continue 2;
                    }

                    if ($dayRange->containsTime($range->start()) && $dayRange->containsTime($range->end())) {
                        $newRanges[] = TimeRange::fromString($dayRange->start()->format() . '-' . $range->start()->format())->format();
                        $newRanges[] = TimeRange::fromString($range->end()->format() . '-' . $dayRange->end()->format())->format();
                        continue 2;
                    }
                    if ($dayRange->containsTime($range->start())) {
                        $newRanges[] = TimeRange::fromString($dayRange->start()->format() . '-' . $range->start()->format())->format();
                        continue 2;
                    }
                    if ($dayRange->containsTime($range->end())) {
                        $newRanges[] = TimeRange::fromString($range->end()->format() . '-' . $dayRange->end()->format())->format();
                        continue 2;
                    }
                }

                $newRanges[] = $dayRange->format();
            }

            $exceptions[$day] = $newRanges;
        }

        foreach ($exceptions as $day => $exceptionRanges) {
            $weekDay = strtolower(Carbon::parse($day)->englishDayOfWeek);
            if (!empty($exceptionRanges)) {
                if ($exceptionRanges === $ranges[$weekDay]) {
                    unset($exceptions[$day]);
                }
            }
        }

        return $exceptions;
    }
}
